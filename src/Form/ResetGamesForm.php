<?php

namespace Drupal\vchess\Form;

use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\gamer\Entity\GamerStatistics;

class ResetGamesForm extends ConfirmFormBase {

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Area you sure you want reset all games?');
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return Url::fromRoute('vchess.challenges');
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'vchess_reset_games_form';
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText() {
    return $this->t('Reset ALL games!?!');
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $result = db_truncate('vchess_games')->execute();
    $result = db_truncate('vchess_moves')->execute();

    $result = \Drupal::entityTypeManager()->getStorage('gamer_stats')->delete(GamerStatistics::loadMultiple());

    drupal_set_message(t('ALL games have been reset!'));
  }
}
