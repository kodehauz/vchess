<?php

namespace Drupal\pos\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;

/**
 * @ContentEntityType(
 *   id = "vchess_position",
 *   label = @Translation("Chess position"),
 *   handlers = {
 *     "list_builder" = "\Drupal\Core\Entity\EntityListBuilder",
 *     "view_builder" = "\Drupal\Core\Entity\EntityViewBuilder",
 *     "form" = {
 *        "add" = "\Drupal\pos\Form\ChessPositionForm",
 *        "edit" = "\Drupal\pos\Form\ChessPositionForm",
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\DefaultHtmlRouteProvider"
 *     },
 *   },
 *   base_table = "vchess_position",
 *   data_table = "vchess_position_field_data",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *     "uuid" = "uuid",
 *   },
 * )
 */
class ChessPosition extends ContentEntityBase {
  /**
   * Gets the board in FEN notation.
   *
   * @return string.
   */
  public function getBoard() {
    return $this->get('board')->value;
  }

  /**
   * Sets the board FEN notation.
   *
   * @return $this
   */
  public function setBoard($value){
    $this->set('board', $value);
    return $this;
  }

  /**
   * Gets the available castling options.
   *
   * @return string.
   */
  public function getCastling() {
    return $this->get('castling')->value;
  }

  /**
   * Sets the available castling options.
   *
   * @param $value
   *   The castling options e.g. 'KQkq': both 'b' and 'w' can castle both ways.
   *   'Q': 'w' can only castle queenside, 'b' can't castle again, etc.
   *
   * @return $this
   */
  public function setCastling($value) {
    $this->set('castling', $value);
    return $this;
  }

  /**
   * Gets the en-passant square.
   *
   * The en-passant square is the square immediately behind a pawn that has made
   * an en-passant move. '-' if no en-passant was played in the last move.
   *
   * @return string.
   */
  public function getEnPassantSquare() {
    return $this->get('en_passant_square')->value;
  }

  /**
   * Sets the en-passant square.
   *
   * @param $value
   *   The en-passant square.
   *
   * @return $this
   */
  public function setEnPassantSquare($value) {
    $this->set('en_passant_square', $value);
    return $this;
  }

  /**
   * Gets the position label.
   *
   * @return string.
   */
  public function getLabel() {
    return $this->get('label')->value;
  }

  /**
   * Sets the position label.
   *
   * @param $value
   *   The name of the position label to set.
   *
   * @return $this
   */
  public function setLabel($value) {
    $this->set('label', $value);
    return $this;
  }

  /**
   * Gets the description of the board position.
   *
   * @return string
   */
  public function getDescription() {
    return $this->get('description')->value;
  }

  /**
   * Sets the description of the board position.
   *
   * @param $value
   *   The description.
   *
   * @return $this
   */
  public function setDescription($value) {
    $this->set('description', $value);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['board'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Board'))
      ->setDescription(t('Board position in FEN format'))
      ->setSetting('max_length', 128)
      ->setRequired(TRUE);

    $fields['castling'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Castling'))
      ->setDefaultValue('KQkq')
      ->setSetting('max_length', 5)
      ->setRequired(TRUE);

    $fields['en_passant_square'] = BaseFieldDefinition::create('string')
      ->setLabel(t('En passant square'))
      ->setDescription(t('EP (en passant) target square. If there is no ep target square, '
        . 'this is "-". If a pawn has just made a 2-square move, this is the position "behind" the pawn. This is recorded regardless of whether there is a pawn in position to make an EP capture.'))
      ->setDefaultValue('-')
      ->setSetting('max_length', 1)
      ->setRequired(TRUE);

    $fields['label'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Label'))
      ->setDescription(t('A short descriptive label'))
      ->setSetting('max_length', 128)
      ->setRequired(TRUE);

    $fields['description'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Description'))
      ->setSetting('max_length', 255)
      ->setDescription(t('A description of the key features of the position'))
      ->setRequired(TRUE);

    return $fields;
  }

  /**
   * Get positions keyed by the FEN board notation with the labels as values.
   *
   * Load an array with the positions data, in the format:
   *   array("FEN board 1" => "Title 1", "FEN board 2" => "Title 2", ...);
   */
  public static function getPositionLabels() {
    $positions = [];

    /** @var \Drupal\pos\Entity\ChessPosition[] $results */
    $results = ChessPosition::loadMultiple();

    foreach ($results as $position) {
      $positions[$position->getBoard()] = $position->getLabel();
    }

    return $positions;
  }

}
