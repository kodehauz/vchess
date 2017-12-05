<?php

namespace Drupal\vchess\Form;


use Drupal\Core\Form\FormStateInterface;

trait GameCreationWidgetsTrait {


  function addGameTimeWidgets(array &$form, FormStateInterface $form_state) {
    $form['is_handicap'] = [
      '#type' => 'checkbox',
      '#title' => 'Handicap game',
      '#default_value' => FALSE,
    ];

    $this->addTimeSelect($form, 'game_time', $this->t('Game time'), FALSE);

    $this->addTimeSelect($form, 'white_time', $this->t('White game time'), TRUE);

    $this->addTimeSelect($form, 'black_time', $this->t('Black game time'), TRUE);

  }

  protected function addTimeSelect(&$form, $name, $title, $visible_on_check = FALSE) {
    $form[$name][$name . '_value'] = [
      '#type' => 'textfield',
      '#size' => '4',
      '#default_value' => 120,
      '#title' => $title,
      '#states' => [
        'visible' => [
          ':input[name="is_handicap"]' => ['checked' => $visible_on_check],
        ],
      ],
    ];

    $form[$name][$name . '_unit'] = [
      '#type' => 'select',
      '#options' => [
        '0' => $this->t('Unlimited'),
        '1' => $this->t('seconds'),
        '60' => $this->t('minutes'),
        '3600' => $this->t('hours'),
        '3600___' => $this->t('days'),
      ],
      '#states' => [
        'visible' => [
          ':input[name="is_handicap"]' => ['checked' => $visible_on_check],
        ],
      ],
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
      '#states' => [
        'visible' => [
          ':input[name="is_handicap"]' => ['checked' => $visible_on_check],
        ],
      ],
    ];



  }

}