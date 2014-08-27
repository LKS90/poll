<?php

/**
 * @file
 * Definition of Drupal\poll\Tests\PollTestBase.
 */

namespace Drupal\poll\Tests;

use Drupal\poll\PollInterface;
use Drupal\simpletest\WebTestBase;

/**
 * Defines a base class for testing the Poll module.
 */
abstract class PollTestBase extends WebTestBase {

  /** @var \Drupal\user\UserInterface $entity */
  protected $admin_user;

  /** @var \Drupal\user\UserInterface $entity */
  protected $web_user;

  /** @var \Drupal\poll\PollInterface $entity */
  protected $poll;

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = array('poll', 'node');

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->admin_user = $this->drupalCreateUser(array('administer polls', 'access polls'));
    $this->web_user = $this->drupalCreateUser(array('access polls', 'cancel own vote'));
    $this->poll = $this->pollCreate();
  }

  /**
   * Creates a poll.
   *
   * @param string $title
   *   The title of the poll.
   * @param array $choices
   *   A list of choice labels.
   * @param boolean $preview
   *   (optional) Whether to test if the preview is working or not. Defaults to
   *   TRUE.
   *
   * @return
   *   The node id of the created poll, or FALSE on error.
   */
  function pollCreate($choice_count = 7, $preview = TRUE) {

    $this->drupalLogin($this->admin_user);

    // Get the form first to initialize the state of the internal browser.
    $this->drupalGet('poll/add');

    $question = $this->randomMachineName();
    $choices = $this->generateChoices($choice_count);
    list($edit, $index) = $this->pollGenerateEdit($question, $choices);

    // Re-submit the form until all choices are filled in.
    if (count($choices) > 1) {
      while ($index < count($choices)) {
        $this->drupalPostForm(NULL, $edit, t('Add another item'));
        list($edit, $index) = $this->pollGenerateEdit($question, $choices, $index);
      }
    }

//    if ($preview) {
//      $this->drupalPostForm('poll/add', $edit, t('Preview'));
//      $this->assertPollChoiceOrder($choices, $index, TRUE);
//      list($edit, $index) = $this->pollGenerateEdit($title, $choices, $index);
//    }

    $this->drupalPostForm(NULL, $edit, t('Save'));

    // Load the first node returned from the database.
    $polls = entity_load_multiple_by_properties('poll', array('question' => $question));
    $poll = reset($polls);
    $this->assertText(t('The poll @question has been added.', array('@question' => $question)), 'Poll has been created.');
    $this->assertTrue($poll->id, 'Poll has been found in the database.');

    return $poll instanceof PollInterface ? $poll : FALSE;
  }

  /**
   * Generates POST values for the poll node form, specifically poll choices.
   *
   * @param $title
   *   The title for the poll node.
   * @param $choices
   *   An array containing poll choices, as generated by
   *   PollTestBase::generateChoices().
   * @param $index
   *   (optional) The amount/number of already submitted poll choices. Defaults
   *   to 0.
   *
   * @return
   *   An indexed array containing:
   *   - The generated POST values, suitable for
   *     Drupal\simpletest\WebTestBase::drupalPostForm().
   *   - The number of poll choices contained in 'edit', for potential re-usage
   *     in subsequent invocations of this function.
   */

  private function pollGenerateEdit($question, array $choices, $index = 0) {
    $max_new_choices = 1;
    $already_submitted_choices = array_slice($choices, 0, $index);
    $new_choices = array_values(array_slice($choices, $index, $max_new_choices));
    $edit = array(
      'question[0][value]' => $question,
    );
    foreach ($already_submitted_choices as $k => $text) {
      $edit['field_choice[' . $k . '][choice]'] = $text;
    }
    foreach ($new_choices as $k => $text) {
      $edit['field_choice[' . $k . '][choice]'] = $text;
    }
    return array($edit, count($already_submitted_choices) + count($new_choices));
  }

  /*
   * Generates random choices for the poll.
   */
  private function generateChoices($count = 7) {
    $choices = array();
    for ($i = 1; $i <= $count; $i++) {
      $choices[] = $this->randomMachineName();
    }
    return $choices;
  }

  /**
   * Asserts correct poll choice order in the node form after submission.
   *
   * Verifies both the order in the DOM and in the 'weight' form elements.
   *
   * @param $choices
   *   An array containing poll choices, as generated by
   *   PollTestBase::generateChoices().
   * @param $index
   *   (optional) The amount/number of already submitted poll choices. Defaults
   *   to 0.
   * @param $preview
   *   (optional) Whether to also check the poll preview.
   *
   * @see PollTestBase::pollGenerateEdit()
   */
  function assertPollChoiceOrder(array $choices, $index = 0, $preview = FALSE) {
    $expected = array();
    $weight = 0;
    foreach ($choices as $id => $label) {
      if ($id < $index) {
        // Directly assert the weight form element value for this choice.
        $this->assertFieldByName('field_choice[' . $id . '][_weight]', $weight, format_string('Found field_choice @id with weight @weight.', array(
          '@id' => $id,
          '@weight' => $weight,
        )));
        // The expected weight of each choice is higher than the previous one.
        $weight++;
        // Append to our (to be reversed) stack of labels.
        $expected[$weight] = $label;
      }
    }
    ksort($expected);

    // Verify DOM order of poll choices (i.e., #weight of form elements).
    $elements = $this->xpath('//input[starts-with(@name, :prefix) and contains(@name, :suffix)]', array(
      ':prefix' => 'choice[chid:',
      ':suffix' => '][chtext]',
    ));
    $expected_order = $expected;
    foreach ($elements as $element) {
      $next_label = array_shift($expected_order);
      $this->assertEqual((string) $element['value'], $next_label);
    }

    // If requested, also verify DOM order in preview.
    if ($preview) {
      $elements = $this->xpath('//div[contains(@class, :teaser)]/descendant::div[@class=:text]', array(
        ':teaser' => 'node-teaser',
        ':text' => 'text',
      ));
      $expected_order = $expected;
      foreach ($elements as $element) {
        $next_label = array_shift($expected_order);
        $this->assertEqual((string) $element, $next_label, format_string('Found choice @label in preview.', array(
          '@label' => $next_label,
        )));
      }
    }
  }

}