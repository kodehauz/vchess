<?php

namespace Drupal\vchess\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

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

    $colors = array('w' => t('white'), 'b' => t('black'));

    $form['colorfield'] = array(
      '#type' => 'fieldset',
      '#title' => t('Choose your color'),
    );

    $form['colorfield']['color'] = array(
      '#type' => 'radios',
      '#default_value' => 'w',
      '#options' => $colors,
    );

    $form['opponent'] = array(
      '#type' => 'textfield',
      '#title' => t('opponent'),
      '#description' => t('Type opponent\'s name. Opponent must be registered on this site.'),
      '#required' => TRUE,
    );

    $form['submit'] = array(
      '#type' => 'submit',
      '#value' => t('Create Game'),
    );

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $users = \Drupal::entityTypeManager()
      ->getStorage('user')
      ->loadByProperties(['name' => $form_state->getValue('opponent')]);

    if (count($users) == 0) {
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

    if ($form_state->getValue('color')=='w') {
      // user plays white
      $white_user = $this->currentUser();

      // opponent plays black
      $black_user = reset($opponent);
    }
    else {
      // user plays black
      $black_user = $this->currentUser();

      // opponent plays white
      $white_user = $opponent;
    }

    //@todo complete porting.
    $game = new Game();
    $game->set_players($white_user, $black_user);
    $gid = $game->gid();
    drupal_set_message(t('Game has been created.'));
    $form_state['redirect'] = 'vchess/game/' . $gid;
  }

}
