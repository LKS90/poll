<?php

/**
 * @file
 * Definition of Drupal\poll\Tests\PollTestBase.
 */

namespace Drupal\poll\Tests;

use Drupal\simpletest\WebTestBase;

class PollTestBase extends WebTestBase {
  function setUp() {
    $modules = func_get_args();
    if (isset($modules[0]) && is_array($modules[0])) {
      $modules = $modules[0];
    }
    $modules[] = 'node';
    $modules[] = 'poll';
    parent::setUp($modules);
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
  function pollCreate($title, $choices, $preview = TRUE) {
    $this->assertTrue(TRUE, 'Create a poll');

    $admin_user = $this->drupalCreateUser(array('create poll content', 'administer nodes'));
    $web_user = $this->drupalCreateUser(array('create poll content', 'access content', 'edit own poll content'));
    $this->drupalLogin($admin_user);

    // Get the form first to initialize the state of the internal browser.
    $this->drupalGet('node/add/poll');

    // Prepare a form with two choices.
    list($edit, $index) = $this->_pollGenerateEdit($title, $choices);

    // Verify that the vote count element only allows non-negative integers.
    $edit['choice[new:1][chvotes]'] = -1;
    $edit['choice[new:0][chvotes]'] = $this->randomString(7);
    $this->drupalPost(NULL, $edit, t('Save'));
    $this->assertText(t('Vote count for new choice must be higher or equal to 0.'));
    $this->assertText(t('Vote count for new choice must be a number.'));

    // Repeat steps for initializing the state of the internal browser.
    $this->drupalLogin($web_user);
    $this->drupalGet('node/add/poll');
    list($edit, $index) = $this->_pollGenerateEdit($title, $choices);

    // Re-submit the form until all choices are filled in.
    if (count($choices) > 2) {
      while ($index < count($choices)) {
        $this->drupalPost(NULL, $edit, t('Add another choice'));
        $this->assertPollChoiceOrder($choices, $index);
        list($edit, $index) = $this->_pollGenerateEdit($title, $choices, $index);
      }
    }

    if ($preview) {
      $this->drupalPost(NULL, $edit, t('Preview'));
      $this->assertPollChoiceOrder($choices, $index, TRUE);
      list($edit, $index) = $this->_pollGenerateEdit($title, $choices, $index);
    }

    $this->drupalPost(NULL, $edit, t('Save'));
    $node = $this->drupalGetNodeByTitle($title);
    $this->assertText(t('@type @title has been created.', array('@type' => node_type_get_name('poll'), '@title' => $title)), 'Poll has been created.');
    $this->assertTrue($node->nid, t('Poll has been found in the database.'));

    return isset($node->nid) ? $node->nid : FALSE;
  }

  /**
   * Generates POST values for the poll node form, specifically poll choices.
   *
   * @param $title
   *   The title for the poll node.
   * @param $choices
   *   An array containing poll choices, as generated by
   *   PollTestBase::_generateChoices().
   * @param $index
   *   (optional) The amount/number of already submitted poll choices. Defaults
   *   to 0.
   *
   * @return
   *   An indexed array containing:
   *   - The generated POST values, suitable for
   *     Drupal\simpletest\WebTestBase::drupalPost().
   *   - The number of poll choices contained in 'edit', for potential re-usage
   *     in subsequent invocations of this function.
   */
  function _pollGenerateEdit($title, array $choices, $index = 0) {
    $max_new_choices = ($index == 0 ? 2 : 1);
    $already_submitted_choices = array_slice($choices, 0, $index);
    $new_choices = array_values(array_slice($choices, $index, $max_new_choices));

    $edit = array(
      'title' => $title,
    );
    foreach ($already_submitted_choices as $k => $text) {
      $edit['choice[chid:' . $k . '][chtext]'] = $text;
    }
    foreach ($new_choices as $k => $text) {
      $edit['choice[new:' . $k . '][chtext]'] = $text;
    }
    return array($edit, count($already_submitted_choices) + count($new_choices));
  }

  function _generateChoices($count = 7) {
    $choices = array();
    for ($i = 1; $i <= $count; $i++) {
      $choices[] = $this->randomName();
    }
    return $choices;
  }

  /**
   * Assert correct poll choice order in the node form after submission.
   *
   * Verifies both the order in the DOM and in the 'weight' form elements.
   *
   * @param $choices
   *   An array containing poll choices, as generated by
   *   PollTestBase::_generateChoices().
   * @param $index
   *   (optional) The amount/number of already submitted poll choices. Defaults
   *   to 0.
   * @param $preview
   *   (optional) Whether to also check the poll preview.
   *
   * @see PollTestBase::_pollGenerateEdit()
   */
  function assertPollChoiceOrder(array $choices, $index = 0, $preview = FALSE) {
    $expected = array();
    $weight = 0;
    foreach ($choices as $id => $label) {
      if ($id < $index) {
        // The expected weight of each choice is higher than the previous one.
        $weight++;
        // Directly assert the weight form element value for this choice.
        $this->assertFieldByName('choice[chid:' . $id . '][weight]', $weight, t('Found choice @id with weight @weight.', array(
          '@id' => $id,
          '@weight' => $weight,
        )));
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
        $this->assertEqual((string) $element, $next_label, t('Found choice @label in preview.', array(
          '@label' => $next_label,
        )));
      }
    }
  }

  function pollUpdate($nid, $title, $edit) {
    // Edit the poll node.
    $this->drupalPost('node/' . $nid . '/edit', $edit, t('Save'));
    $this->assertText(t('@type @title has been updated.', array('@type' => node_type_get_name('poll'), '@title' => $title)), 'Poll has been updated.');
  }
}
