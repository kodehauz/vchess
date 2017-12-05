<?php

namespace Drupal\vchess\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\pos\Entity\ChessPosition;
use Drupal\user\Entity\User;
use Drupal\vchess\Entity\Game;
use Drupal\vchess\GameManagementTrait;

class CreateChallengeForm extends FormBase {

  use GameManagementTrait;
  use GameCreationWidgetsTrait;

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'vchess_create_challenge_form';
  }
  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['description'] = array(
      '#type' => 'item',
      '#title' => $this->t('Simply click on the button below and we will create
      a game for you against a random opponent.'),
    );

    $this->addGameTimeWidgets($form, $form_state);

    $positions = ChessPosition::getPositionLabels();
    $form['position'] = [
      '#type' => 'select',
      '#title' => $this->t('Starting position'),
      '#options' => $positions,
      '#description' => $this->t('Select the game starting position.'),
    ];

    $form['submit'] = array(
      '#type' => 'submit',
      '#value' => $this->t('Create Challenge'),
    );

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $user = User::load(\Drupal::currentUser()->id());
    // Check that user does not already have too many challenges pending.
    if (count(Game::loadChallenges($user)) < VCHESS_PENDING_LIMIT) {
      static::createChallenge($user, $form_state->getValue('time_per_move'), $form_state->getValue('position'));

      drupal_set_message($this->t('Challenge has been created.'));

      //     watchdog("VChess", "In game.inc for game %gid, at start of set_player() setting player uid=%uid." .
      //         " Currently white_uid=%white_uid and black_uid=%black_uid",
      //         array('%gid' => $this->gid(),
      //             '%uid' => $uid,
      //             '%white_uid' => $this->white_uid,
      //             '%black_uid' => $this->black_uid));

      //     watchdog("VChess", "in game.inc for game %gid, at end of set_player() setting " .
      //         " player uid=%uid.  Now white_uid=%white_uid and black_uid=%black_uid",
      //         array('%gid' => $this->gid(),
      //             '%uid' => $uid,
      //             '%white_uid' => $this->white_uid,
      //             '%black_uid' => $this->black_uid));

    }
    else {
      drupal_set_message($this->t('You already have the allowed maxiumum of @max challenges pending.',
        ['@max' => VCHESS_PENDING_LIMIT]));
    }

    $form_state->setRedirect('vchess.challenges');
  }

}
