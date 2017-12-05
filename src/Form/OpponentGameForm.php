<?php

namespace Drupal\vchess\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\user\Entity\User;
use Drupal\vchess\Entity\Game;
use Drupal\vchess\GameManagementTrait;

class OpponentGameForm extends FormBase {

  use GameManagementTrait;

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'vchess_opponent_game_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $colors = ['w' => $this->t('White'), 'b' => $this->t('Black')];

    $form['colorfield'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Choose your color'),
    ];

    $form['colorfield']['color'] = [
      '#type' => 'radios',
      '#default_value' => 'w',
      '#options' => $colors,
    ];

    $form['opponent'] = [
      '#type' => 'entity_autocomplete',
      '#target_type' => 'user',
      '#title' => $this->t('Opponent'),
      '#description' => $this->t('Type opponent\'s name. Opponent must be registered on this site.'),
      '#required' => TRUE,
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Create Game'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    if (User::load($form_state->getValue('opponent')) === NULL) {
      $form_state->setErrorByName('opponent', $this->t('Opponent does not exist on this site'));
    }
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
    $opponent = User::load($form_state->getValue('opponent'));

    //Collecting data for black and white players.
    $black_time = $form_state->getValue('black_time');


    if ($form_state->getValue('color') === 'w') {
      // User plays white.
      $white_user = $user;

      // Opponent plays black.
      $black_user = $opponent;
    }
    else {
      // User plays black.
      $black_user = $user;

      // Opponent plays white.
      $white_user = $opponent;
    }

    $game = Game::create();
    static::initializeGame($game, $white_user, $black_user, $user);
    drupal_set_message($this->t('Game %label has been created.', ['%label' => $game->label()]));
    $form_state->setRedirect('vchess.game', ['vchess_game' => $game->id()]);
  }

}
