<?php

namespace Drupal\gamer\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\gamer\Rating;
use Drupal\user\UserInterface;
use Drupal\vchess\Entity\Game;
use Drupal\vchess\Game\GamePlay;

/**
 * Defines the gamer statistics entity.
 *
 * @ContentEntityType(
 *   id = "gamer_statistics",
 *   label = @Translation("Gamer statistics"),
 *   handlers = {
 *     "views_data" = "Drupal\views\EntityViewsData",
 *   },
 *   entity_keys = {
 *     "id" = "id",
 *     "uuid" = "uuid",
 *     "langcode" = "langcode"
 *   },
 *   base_table = "gamer_statistics",
 * )
 */
class GamerStatistics extends ContentEntityBase {

    // Define game statuses
  const GAMER_WHITE_WIN = '1-0';
  const GAMER_BLACK_WIN = '0-1';
  const GAMER_DRAW = '1/2-1/2';

  /**
   * @return \Drupal\user\UserInterface
   */
  public function getOwner() {
    return $this->get('owner')->entity;
  }

  /**
   * @todo
   */
  public function setOwner($value) {
    $this->set('owner', $value);
    return $this;
  }

  /**
   * @return int
   */
  public function getCurrent() {
    return $this->get('current')->value;
  }

  /**
   * @todo
   */
  public function setCurrent($value) {
    $this->set('current', $value);
    return $this;
  }

  /**
   * @return int
   */
  public function getWon() {
    return $this->get('won')->value;
  }

  /**
   * @todo
   */
  public function setWon($value) {
    $this->set('won', $value);
    return $this;
  }

  /**
   * @return int
   */
  public function getDrawn() {
    return $this->get('drawn')->value;
  }

  /**
   * @todo
   */
  public function setDrawn($value) {
    $this->set('drawn', $value);
    return $this;
  }

  /**
   * @return int
   */
  public function getLost() {
    return $this->get('lost')->value;
  }

  /**
   * @todo
   */
  public function setLost($value) {
    $this->set('lost', $value);
    return $this;
  }

  /**
   * @return int
   */
  public function getRating() {
    return $this->get('rating')->value;
  }

  /**
   * @todo
   */
  public function setRating($value) {
    $this->set('rating', $value);
    return $this;
  }

  /**
   * @return int
   */
  public function getPlayed() {
    return $this->get('played')->value;
  }

  /**
   * @todo
   */
  public function setPlayed($value) {
    $this->set('played', $value);
    return $this;
  }

  /**
   * @return int
   */
  public function getRchanged() {
    return $this->get('rchange')->value;
  }

  /**
   * @todo
   */
  public function setRchanged($value) {
    $this->set('rchange', $value);
    return $this;
  }

  /**
   * Loads the game statistics for a particular user.
   *
   * @param \Drupal\user\UserInterface $user
   *   The user for which game statistics should be loaded.
   *
   * @return \Drupal\gamer\Entity\GamerStatistics|null
   */
  public static function loadForUser(UserInterface $user) {
    $stats = \Drupal::entityTypeManager()
      ->getStorage('gamer_statistics')
      ->loadByProperties(['owner' => $user->id()]);
    if ($stats) {
      return reset($stats);
    }
    else {
      $stats = static::create();
      $stats
        ->setOwner($user)
        ->setRating(\Drupal::config('gamer.settings')->get('default_user_rating'))
        ->save();

      return $stats;
    }
  }

  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['owner'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Form ID'))
      ->setDescription(t('The ID of the associated form.'))
      ->setSetting('target_type', 'user')
      ->setRequired(TRUE);

    $fields['current'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Current'))
      ->setDescription(t(''))
      ->setDefaultValue(0)
      ->setRequired(TRUE);

    $fields['won'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Won'))
      ->setDescription(t(''))
      ->setDefaultValue(0)
      ->setRequired(TRUE);

    $fields['drawn'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Drawn'))
      ->setDescription(t(''))
      ->setDefaultValue(0)
      ->setRequired(TRUE);

    $fields['lost'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Lost'))
      ->setDescription(t(''))
      ->setDefaultValue(0)
      ->setRequired(TRUE);

    $fields['rating'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Rating'))
      ->setDescription(t(''))
      ->setDefaultValue(0)
      ->setRequired(TRUE);

    $fields['played'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Played'))
      ->setDescription(t(''))
      ->setDefaultValue(0)
      ->setRequired(TRUE);

    $fields['rchange'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Rchange'))
      ->setDescription(t(''))
      ->setDefaultValue(0)
      ->setRequired(TRUE);

    return $fields;
  }


  /**
   * Update user stats
   *
   * The final rating change is truncated to four digits after the comma.
   *
   * @param $white_user
   *   White player user id
   * @param $black_user
   *   Black player user id
   * @param $score
   *   Score is one of:
   *   - GAMER_WHITE_WIN
   *   - GAMER_BLACK_WIN
   *   - GAMER_DRAW
   */
  public static function updateUserStatistics(UserInterface $white_user, UserInterface $black_user, $score) {
    $white = GamerStatistics::loadForUser($white_user);
    $black = GamerStatistics::loadForUser($black_user);

    // Update wins/draws/losses
    if ($score == GamerStatistics::GAMER_WHITE_WIN) {
      $white->setWon($white->getWon() + 1);
      $black->setLost($black->getLost() + 1);
    }
    elseif ($score == GAMER_DRAW) {
      $white->setDrawn($white->getDrawn() + 1);
      $black->setDrawn($white->getDrawn() + 1);
    }
    else { // Black won
      $white->setLost($white->getLost() + 1);
      $black->setWon($black->getWon() + 1);
    }

    $white->setCurrent($white->getCurrent() - 1);
    $black->setCurrent($black->getCurrent() - 1);

    $white->setPlayed($white->getPlayed() + 1);
    $black->setPlayed($black->getPlayed() + 1);

    // Update rating change according to the winning probability
    $win_probability = Rating::calculateWinProbability($white->getRating() - $black->getRating());
    $rchange = round(Rating::calcRatingChangeMultiplier() * ($score - $win_probability));

    // Update rating
    $white->setRchanged($rchange);
    $black->setRchanged(-$rchange);

    $white->setRating($white->getRating() + $rchange);
    $black->setRating($black->getRating() - $rchange);

    $white->save();
    $black->save();
  }

  /**
   * Update stats to note that another game is in progress
   *
   * @param $uid1
   *   The user id of the first player
   * @param $uid2
   *   The user id of the second player
   */
  public static function addInProgress(UserInterface $user1, UserInterface $user2) {
    // Load stats. Is always successful (returns zero array if not found).
    $player1_stats = static::loadForUser($user1);
    $player2_stats = static::loadForUser($user2);

    //  @todo: rewrite gamer model to be more OO and then finish this function!

    // Get the new stats
    //   $winner_stats_new = gamer_update_stats($winner->uid, $winner_stats, $loser_stats, 1);
    //   $loser_stats_new = gamer_update_stats($loser->uid, $loser_stats, $winner_stats, 0);

    //   // Save changes
    //   gamer_save_user_stats($winner->uid, $winner_stats_new);
    //   gamer_save_user_stats($loser->uid, $loser_stats_new);
  }

  /**
   * Update the player statistics
   *
   * @param $game
   *   The game which has just finished
   */
  public static function updatePlayerStatistics(Game $game) {
    switch ($game->getStatus()) {
      case GamePlay::STATUS_WHITE_WIN:
        $score = GamerStatistics::GAMER_WHITE_WIN;
        break;
      case GamePlay::STATUS_BLACK_WIN:
        $score = GamerStatistics::GAMER_BLACK_WIN;
        break;
      case GamePlay::STATUS_DRAW:
        $score = GamerStatistics::GAMER_DRAW;
        break;
      default:
        $score = '';
    }

    static::updateUserStatistics($game->getWhiteUser(), $game->getBlackUser(), $score);
  }

}
