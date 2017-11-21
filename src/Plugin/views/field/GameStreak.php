<?php

namespace Drupal\vchess\Plugin\views\field;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\Form\FormStateInterface;
use Drupal\user\Entity\User;
use Drupal\vchess\Entity\Game;
use Drupal\views\Annotation\ViewsField;
use Drupal\views\Plugin\views\field\Standard;
use Drupal\views\ResultRow;

/**
 * @ViewsField("vchess_game_streak");
 */
class GameStreak extends Standard {

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();
    $options['delimiter'] = ['default' => 'none'];

    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);

    $form['delimiter'] = [
      '#title' => $this->t('Delimiter for values'),
      '#type' => 'select',
      '#options' => [
        'none' => $this->t('None'),
        'space' => $this->t('A single space'),
        'hyphen' => $this->t('A hyphen'),
      ],
      '#default_value' => $this->options['delimiter'],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values) {
    $user = User::load($this->getValue($values));
    $streak = Game::getPlayerStreak($user);
    if ($this->options['delimiter'] && $this->options['delimiter'] !== 'none') {
      return implode($this->delimiter(), $streak);
    }
    else {
      $formatter = function ($final, $item) {
        $final .= "<li>$item</li>";
        return $final;
      };
      return new FormattableMarkup('<ul>' . array_reduce($streak, $formatter, '') . '</ul>', []);
    }
  }

  /**
   * Returns the delimiter matching the chosen option.
   *
   * @return null|string
   */
  protected function delimiter() {
    switch ($this->options['delimiter']) {
      case 'none':
        return NULL;

      case 'space':
        return ' ';

      case 'hyphen':
        return '-';

      default:
        return NULL;
    }
  }

}
