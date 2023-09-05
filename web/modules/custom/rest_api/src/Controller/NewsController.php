<?php

namespace Drupal\rest_api\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\file\Entity\File;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Returns responses for Rest api routes.
 */
final class NewsController extends ControllerBase {

  /**
   * Manages entity type plugin definitions.
   *
   * @var \Drupal\Core\Entity\EntityTypeManager
   */
  protected $entityTypeManager;

  /**
   * Sets class variables.
   *
   * @param \Drupal\Core\Entity\EntityTypeManager $entityTypeManager
   *   Manages entity type plugin definitions.
   */
  public function __construct(EntityTypeManager $entityTypeManager) {
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * Injects dependencies to the class.
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
    );
  }

  /**
   * Builds the response.
   */
  public function __invoke(Request $request): JsonResponse {
    $config = \Drupal::configFactory()->getEditable('custom_secret_key.settings');

    if ($request->query->get('auth_key') !== $config->get('auth_key')) {
      return new JsonResponse("Access denied", 404);
    }
    else if ($request->query->get('tags')) {
      // Fetching condition parameters from the request Url.
      $params = $this->setConditions($request);
      // Fetching node ids.
      $nids = $this->fetchNodeIds($params);
      // Fetching node object by node id.
      $nodes = $this->entityTypeManager->getStorage('node')->loadMultiple($nids);
      $nodes = $this->preProcessNode($nodes);
      return new JsonResponse($nodes);
    }
    return new JsonResponse("No news for the tag was found");
  }

  /**
   * Sets conditional parameters.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   Request represents an HTTP request.
   */
  public function setConditions(Request $request) {
    $params = [];

    if ($tags = $request->query->get('tags')) {
      // Fetching the term id from term name.
      $terms = $this->entityTypeManager->getStorage('taxonomy_term')
        ->loadByProperties(['name' => explode(" ", $tags)]);
      // Setting up the taxonomy term condition parameters.
      if (!empty($terms)) {
        $term_ids = [];
        foreach ($terms as $term) {
          array_push($term_ids, $term->id());
        }
        $params['tags'] = [
          'key' => 'field_news_tags.target_id',
          'value' => $term_ids,
          'expression' => 'IN',
        ];
      }
    }
    return $params;
  }

  /**
   * Fetches node ids based on query parameters.
   *
   * @param array $params
   *   Contains request parameters.
   */
  private function fetchNodeIds(array $params): array {
    try {
      $storage = $this->entityTypeManager->getStorage('node');
      $nids = $storage
        ->getQuery()
        ->accessCheck(TRUE)
        ->condition('type', 'news');

      foreach ($params as $condition) {
        $nids = $nids->condition(
          $condition['key'],
          $condition['value'],
          $condition['expression'],
        );
      }
      $nids = $nids->execute();
    }
    catch (\Exception $e) {
      $this->loggerFactory->get('rest_api')->warning($e->getMessage());
      return [];
    }

    return array_values($nids);
  }

  /**
   * Pre-processes the node fields.
   *
   * @param array $nodes
   *   Array of nodes.
   *
   * @return array
   *   Returns associative array with field key and value.
   */
  private function preProcessNode(array $nodes) {
    $tags = $this->entityTypeManager->getStorage('taxonomy_term')->loadTree('tags');

    // Mapping tags with target id.
    $processedTags = [];
    foreach ($tags as $tag) {
      $processedTags[$tag->tid] = $tag;
    }

    // Fetching term data.
    $data = [];
    foreach ($nodes as $nid => $node) {
      $data[$nid] = [
        'title' => $node->get('title')->value,
        'body' => $node->get('body')->value,
        'summary' => $node->get('body')->summary,
        'tags' => $this->getTagsName($processedTags, $node->get('field_news_tags')->getValue()),
        'image' => $this->getImageUri($node->get('field_news_images')->getValue()),
        'view_count' => $node->get('field_news_view_count')->value,
        // 'publish_date' => $posted_at,
        'publish_date' => $node->get('field_news_publish_date')->value,
      ];
    }

    return $data;
  }

  /**
   * Returns tags information for a give target id of the taxonomy_term.
   *
   * @param array $taxonomy_terms
   *   Objects of taxonomy terms.
   * @param array $target_ids
   *   Target id of the taxonomy.
   *
   * @return array
   *   Returns associative array with tag names.
   */
  private function getTagsName($taxonomy_terms, $target_ids) :array {
    $tags = [];
    foreach ($target_ids as $tid) {
      $tid = $tid['target_id'];
      $tags[] = $taxonomy_terms[$tid]->name;
    }
    return $tags;
  }

  /**
   * Returns image uri.
   *
   * @return array
   *   Returns associative array with tag names.
   */
  private function getImageUri($target_ids) :array {
    $images = [];
    foreach ($target_ids as $tid) {
      $tid = $tid['target_id'];
      $file = File::load((int) $tid);
      $images[] = $file->getFileUri();
    }
    return $images;
  }

}
