<?php

namespace Drupal\pos\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;

/**
 *
 */

class ChessPosition extends ContentEntityBase {
  /**
   * return varchar.
   */
  public function getBoard() {
    return $this->get('board')->varchar;
  }

  /*
   * @todo
   */
  public function setBoard($value){
    return $this->set('board', $value);
  }

  /**
   * return char.
   */
  public function getCastling() {
    return $this->get('castling')->char;
  }

  /**
   * @todo
   */
  public function setCastling($value) {
    $this->set('castling', $value);
    return $this;
  }

  /**
   * return char.
   */
  public function getEnPassant() {
    return $this->get('en_passant')->char;
  }

  /**
   * @todo
   */
  public function setEnPassant($value) {
    $this->set('en_passant', $value);
    return $this;
  }

  /**
   * return varchar.
   */
  public function getTitle() {
    return $this->get('title')->varchar;
  }

  /**
   * @todo
   */
  public function setTitle($value) {
    $this->set('title', $value);
    return $this;
  }

  /**
   * return text.
   */
  public function getDescription() {
    return $this->get('description')->text;
  }

  /**
   * @todo
   */
  public function setDescription($value) {
    $this->set('description', $value);
    return $this;
  }

/**
   * Define the database tables required for this module.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *
   * @return array|\Drupal\Core\Field\FieldDefinitionInterface[]
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['board'] = BaseFieldDefinition::create('varchar')
      ->setLabel(t('Board'))
      ->setDescription(t('Board position in FEN format'))
      ->setRequired(TRUE);

    $fields['castling'] = BaseFieldDefinition::create('char')
      ->setLabel(t('Castling'))
      ->setDefaultValue('KQkq')
      ->setRequired(TRUE);

    $fields['en_passant'] = BaseFieldDefinition::create('char')
      ->setLabel(t('En_passant'))
      ->setDescription(t('ep (en passant) target square. If there is no ep target square, \' . \'
this is "-". If a pawn has just made a 2-square move, this is the position "behind" the pawn. This is recorded regardless of whether there is a pawn in position to make an ep capture.\''))
      ->setDefaultValue(0)
      ->setRequired(TRUE);

    $fields['title'] = BaseFieldDefinition::create('varchar')
      ->setLabel(t(''))
      ->setDescription(t('A short descriptive title'))
      ->setDefaultValue(0)
      ->setRequired(TRUE);

    $fields['description'] = BaseFieldDefinition::create('text')
      ->setLabel(t(''))
      ->setDescription(t('A description of the key features of the position'))
      ->setDefaultValue(0)
      ->setRequired(TRUE);

    return $fields;
  }

  /**
   * Get positions
   *
   * Load an array with the positions data, in the format:
   *   array("Title 1" => "FEN board 1", "Title 2" => "FEN board 2", ...);
   */
  public static function getPositionLabels() {
    $positions = [];

    /** @var \Drupal\pos\Entity\ChessPosition[] $results */
    $results = ChessPosition::loadMultiple();

    foreach ($results as $position) {
      $positions[$position->getBoard()] = $position->getTitle();
    }

    return $positions;
  }

}
