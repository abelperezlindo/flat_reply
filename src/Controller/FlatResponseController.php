<?php

namespace Drupal\flat_response\Controller;

use Drupal\ajax_comments\Controller\AjaxCommentsController;
use Symfony\Component\HttpFoundation\Request;
use Drupal\Core\Entity\EntityInterface;
use Drupal\ajax_comments\Utility;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\RemoveCommand;
use Drupal\Core\Ajax\AfterCommand;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Undocumented class.
 */
class FlatResponseController extends AjaxCommentsController {

  /**
   * Builds ajax response to display a form to reply to another comment.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request object.
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity this comment belongs to.
   * @param string $field_name
   *   The field_name to which the comment belongs.
   * @param int $pid
   *   The parent comment's comment ID.
   * @param int $p_cardinality
   *   The parent cardinality.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse|\Symfony\Component\HttpFoundation\RedirectResponse
   *   The Ajax response, or a redirect response if not using ajax.
   *
   * @see \Drupal\comment\Controller\CommentController::getReplyForm()
   */
  public function flatReply(Request $request, EntityInterface $entity, $field_name, $pid, $p_cardinality) {

    $is_ajax = Utility::isAjaxRequest($request);
    if ($is_ajax) {
      $response = new AjaxResponse();

      // Get the selectors.
      $selectors = $this->tempStore->getSelectors($request, $overwrite = TRUE);
      $wrapper_html_id = $selectors['wrapper_html_id'];
      $this->replyAccess($request, $response, $entity, $field_name, $pid);

      if (!empty($response->getCommands())) {
        return $response;
      }

      // Remove any existing status messages and ajax reply forms in the
      // comment field, if applicable.
      $response->addCommand(new RemoveCommand($wrapper_html_id . ' .js-ajax-comments-messages'));
      $response->addCommand(new RemoveCommand($wrapper_html_id . ' .ajax-comments-form-reply'));

      // Build the comment entity form.
      // This approach is very similar to the one taken in
      // \Drupal\comment\CommentLazyBuilders::renderForm().
      /** @var \Drupal\comment\Entity\Comment $comment */
      $comment = $this->entityTypeManager()->getStorage('comment')->create([
        'entity_id' => $entity->id(),
        'pid' => $pid,
        'entity_type' => $entity->getEntityTypeId(),
        'field_name' => $field_name,
      ]);
      $comment->get('subject')->setValue('RE: ' . $p_cardinality);
      $comment->get('comment_body')
        ->setValue('@#' . $p_cardinality . ' ');
      // Build the comment form.
      $form = $this->entityFormBuilder()->getForm($comment);
      $response->addCommand(new AfterCommand(static::getCommentSelectorPrefix() . $pid, $form));

      // Don't delete the tempStore variables here; we need them
      // to persist for the saveReply() method, where the form returned
      // here will be submitted.
      // Instead, return the response without calling:
      // $this->tempStore->deleteAll().

      return $response;
    }
    else {
      // If the user attempts to access the comment reply form with JavaScript
      // disabled, degrade gracefully by redirecting to the core comment
      // reply form.
      $redirect = Url::fromRoute(
        'comment.reply',
        [
          'entity_type' => $entity->getEntityTypeId(),
          'entity' => $entity->id(),
          'field_name' => $field_name,
          'pid' => $pid,
        ]
      )
        ->setAbsolute()
        ->toString();
      $response = new RedirectResponse($redirect);
      return $response;
    }

  }

}
