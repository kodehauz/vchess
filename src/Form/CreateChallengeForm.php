<?php

namespace Drupal\vchess\Form;


use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\pos\Entity\ChessPosition;
use Drupal\user\Entity\User;
use Drupal\vchess\Entity\Game;

class CreateChallengeForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'vchess_create_challenge_form';
  }
  /**
   * menu callback vchess_create_challenge_form to display new game form
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = array();

    $form['description'] = array(
      '#type' => 'item',
      '#title' => t('Simply click on the button below and we will create
      a game for you against a random opponent.'),
    );

    $form['time_per_move'] = array(
      '#type' => 'select',
      '#title' => t('Time per move'),
      '#options' => array(
        '1' => t('1 day'),
        '2' => t('2 days'),
        '3' => t('3 days'),
        '5' => t('5 days'),
      ),
      '#default_value' => "3", // added default value.
    );

    $positions = ChessPosition::getPositionLabels();
    $form['position'] = [
      '#type' => 'select',
      '#title' => t('Starting position'),
      '#options' => $positions,
      '#description' => t('Select the game starting position.'),
    ];

    $form['submit'] = array(
      '#type' => 'submit',
      '#value' => t('Create Challenge'),
    );

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $user = User::load(\Drupal::currentUser()->id());
    $pending = 0;
    $games = Game::loadChallenges();
    foreach ($games as $game) {
      // Check if there is a matching challenge.
      if ($game->getChallenger()->id() != $user->id()
        && $game->getTimePerMove() == $form_state->getValue('time_per_move')) {
        vchess_accept_challenge($game->id());
      }
      if ($game->getChallenger()->id() === $user->id()) {
        $pending++;
      }
    }

    // Check that user does not already have too many challenges pending.
    if ($pending < VCHESS_PENDING_LIMIT) {
      Game::create()
        ->setWhiteUser($user)
        ->setTimePerMove( $form_state->getValue('time_per_move'))
        ->setPosition($form_state->getValue('position'))
        ->save();

      drupal_set_message($this->t('Challenge has been created.'));
    }
    else {
      drupal_set_message($this->t('You already have the allowed maxiumum of @max challenges pending.',
        ['@max' => VCHESS_PENDING_LIMIT]));
    }

    $form_state->setRedirect('vchess.challenges');
  }

}
