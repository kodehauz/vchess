<?php

namespace Drupal\vchess\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\user\Entity\User;
use Drupal\vchess\Entity\Game;

class OpponentGameForm extends FormBase {

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

    $colors = ['w' => $this->t('white'), 'b' => $this->t('black')];

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
      '#type' => 'textfield',
      '#title' => $this->t('opponent'),
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
    $users = \Drupal::entityTypeManager()
      ->getStorage('user')
      ->loadByProperties(['name' => $form_state->getValue('opponent')]);

    if (count($users) === 0) {
      $form_state->setErrorByName('opponent', t('Opponent does not exist on this site'));
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
    $opponent = \Drupal::entityTypeManager()
      ->getStorage('user')
      ->loadByProperties(['name' => $form_state->getValue('opponent')]);

    if ($form_state->getValue('color') === 'w') {
      // user plays white
      $white_user = User::load($this->currentUser()->id());

      // opponent plays black
      $black_user = reset($opponent);
    }
    else {
      // user plays black
      $black_user = User::load($this->currentUser()->id());

      // opponent plays white
      $white_user = reset($opponent);
    }

    $game = Game::create()
      ->setWhiteUser($white_user)
      ->setBlackUser($black_user)
      ->save();
    drupal_set_message($this->t('Game has been created.'));
    $form_state->setRedirect(Url::fromRoute('vchess.game', ['game' => $game->id()]));
  }

}
