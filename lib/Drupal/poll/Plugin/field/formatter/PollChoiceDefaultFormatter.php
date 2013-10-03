<?php

/**
 * @file
 * Definition of Drupal\poll\Plugin\field\formatter\VoteChoiceDefaultFormatter.
 */

namespace Drupal\poll\Plugin\field\formatter;

use Drupal\field\Annotation\FieldFormatter;
use Drupal\Core\Annotation\Translation;
use Drupal\field\Plugin\Type\Formatter\FormatterBase;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\Field\FieldInterface;
use Drupal;


/**
 * Plugin implementation of the 'poll_choice' formatter.
 *
 * @FieldFormatter(
 *   id = "poll_choice_default",
 *   module = "poll",
 *   label = @Translation("Poll choice"),
 *   field_types = {
 *     "poll_choice"
 *   }
 * )
 */
class PollChoiceDefaultFormatter extends FormatterBase {

  /**
   * {@inheritdoc}
   */
  public function viewElements(EntityInterface $entity, $langcode, FieldInterface $items) {
    $elements = array();
    foreach ($items as $delta => $item) {
      $elements[$delta] = array('#markup' => $item->choice);
    }
    return $elements;
  }


}
