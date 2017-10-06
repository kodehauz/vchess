<?php

namespace Drupal\vchess\Element;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element\Table;

/**
 * @RenderElement("vchess_move_list")
 */
class MoveList extends Table {

  /**
   * {@inheritdoc}
   */
  public function getInfo() {
    $class = get_class($this);
    $info = parent::getInfo();
    $info['#moves'] = [];
    $info['#game'] = [];
    $info['#process'] = [
      [$class, 'processMoves'],
    ];
    return $info;
  }

  public static function processMoves(&$element, FormStateInterface $form_state, &$complete_form) {
    /** @var \Drupal\vchess\Entity\Move[] $moves */
    $rows = [];
    foreach ($element['#moves'] as $move_no => $moves) {
      if (array_key_exists('b', $moves)) {
        $rows[] = [$move_no, $moves['w']->getAlgebraic(), $moves['b']->getAlgebraic()];
      }
      else {
        $rows[] = [$move_no, $moves['w']->getAlgebraic(), ""];
      }
    }
    $element['#header'] = [t('Move #'), t('White'), t('Black')];
    $element['#rows'] = $rows;
    $element['#empty'] = t("There are no moves played yet.");

    parent::processTable($element, $form_state, $complete_form);
  }

}
