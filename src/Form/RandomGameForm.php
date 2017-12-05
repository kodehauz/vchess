<?php

namespace Drupal\vchess\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\user\Entity\User;
use Drupal\vchess\Entity\Game;
use Drupal\vchess\GameManagementTrait;

/**
 * Form to start a chess game against a random opponent.
 */
class RandomGameForm extends FormBase {

  use GameManagementTrait;
  use GameCreationWidgetsTrait;

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'vchess_random_game_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['description'] = [
      '#type' => 'item',
      '#title' => $this->t('Simply click on the button below and we will create
          a game for you against a random opponent.'),
    ];

    $this->addGameTimeWidgets($form, $form_state);

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Create Game'),
    ];

    return $form;
  }


  /**
   * Form submission handler.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $user = User::load($this->currentUser()->id());

    // Get the uid load a random opponent.
    $possible_opponents = array_keys(\Drupal::entityQuery('user')
      ->condition('uid', 0, '!=')
      ->condition('uid', $user->id(), '!=')
      ->execute());

    $random_uid = $possible_opponents[mt_rand(0, count($possible_opponents) - 1)];
    $opponent = User::load($random_uid);

    if (mt_rand(0, 1)) {
      // Current user plays white and opponent plays black
      $white_user = $user;
      $black_user = $opponent;
    }
    else {
      // Current user plays black and opponent plays white
      $black_user = $user;
      $white_user = $opponent;
    }
    /** @var \Drupal\vchess\Entity\Game $game */
    $game = Game::create();
    $values = $form_state->getValues();
    $game_time = $values['game_time_value'] * $values['game_time_unit'];
    static::initializeGame($game, $white_user, $black_user, $user, $game_time, $values['game_time_per_move']);

    drupal_set_message($this->t('Game %label has been created.', ['%label' => $game->label()]));
    $form_state->setRedirect('vchess.game', ['vchess_game' => $game->id()]);
  }

}
