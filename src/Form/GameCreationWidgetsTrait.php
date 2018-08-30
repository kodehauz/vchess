<?php

namespace Drupal\vchess\Form;

use Drupal\Core\Form\FormStateInterface;

trait GameCreationWidgetsTrait {


  protected function addGameTimeWidgets(array &$form, FormStateInterface $form_state) {

    $this->addTimeSelect($form, 'game', $this->t('Game time'));

    //$this->addTimeSelect($form, 'white', $this->t('White game time'));

    //$this->addTimeSelect($form, 'black', $this->t('Black game time'));

  }

  protected function addTimeSelect(&$form, $name, $title) {
    $form[$name][$name . '_time_value'] = [
      '#type' => 'textfield',
      '#size' => '4',
      '#default_value' => 120,
      '#title' => $title,
    ];

    $form[$name][$name . '_time_unit'] = [
      '#type' => 'select',
      '#options' => [
        '0' => $this->t('Unlimited'),
        '1' => $this->t('seconds'),
        '60' => $this->t('minutes'),
        '3600' => $this->t('hours'),
        '86400' => $this->t('days'),
      ],
      '#default_value' => '60',
    ];

    $form[$name][$name . '_time_per_move'] = [
      '#type' => 'select',
      '#title' => $this->t('Time per move'),
      '#options' => [
        '0' => $this->t('Unlimited'),
        '1' => $this->t('1 day'),
        '2' => $this->t('2 days'),
        '3' => $this->t('3 days'),
        '5' => $this->t('5 days'),
      ],
      '#default_value' => '3',
    ];
  }

}
