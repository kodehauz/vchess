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

  // Define game status strings.
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
   * @return $this
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
   * @return $this
   */
  public function setCurrent($value) {
    $this->set('current', $value);
    return $this;
  }

  /**
   * @param int $increment
   *
   * @return $this
   */
  public function incrementCurrent($increment=1) {
    $this->set('current', $this->get('current')->value + $increment);
    return $this;
  }

  /**
   * @return int
   */
  public function getWon() {
    return $this->get('won')->value;
  }

  /**
   * @return $this
   */
  public function setWon($value) {
    $this->set('won', $value);
    return $this;
  }


  /**
   * @param int $increment
   *
   * @return $this
   */
  public function incrementWon($increment=1) {
    $this->set('won', $this->get('won')->value + $increment);
    return $this;
  }

  /**
   * @return int
   */
  public function getDrawn() {
    return $this->get('drawn')->value;
  }

  /**
   * @return $this
   */
  public function setDrawn($value) {
    $this->set('drawn', $value);
    return $this;
  }

  /**
   * @param int $increment
   *
   * @return $this
   */
  public function incrementDrawn($increment=1) {
    $this->set('drawn', $this->get('drawn')->value + $increment);
    return $this;
  }

  /**
   * @return int
   */
  public function getLost() {
    return $this->get('lost')->value;
  }

  /**
   * @return $this
   */
  public function setLost($value) {
    $this->set('lost', $value);
    return $this;
  }

  /**
   * @param int $increment
   *
   * @return $this
   */
  public function incrementLost($increment=1) {
    $this->set('lost', $this->get('lost')->value + $increment);
    return $this;
  }

  /**
   * @return int
   */
  public function getRating() {
    return $this->get('rating')->value;
  }

  /**
   * @return $this
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
   * @return $this
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
   * @return $this
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
      // @todo Should this be the case???
      $stats = static::create();
      $stats
        ->setOwner($user)
        ->setRating(\Drupal::config('gamer.settings')->get('default_user_rating'))
        ->save();

      return $stats;
    }
  }

  /**
   * {@inheritdoc}
   */
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
   * Update stats to note that another game is in progress
   *
   * @param \Drupal\user\UserInterface $user1
   *   The first player user entity.
   * @param \Drupal\user\UserInterface $user2
   *   The second player user entity.
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
   * Update the player statistics for a completed game.
   *
   * The final rating change for players is truncated to four digits after the
   * comma.
   *
   * @param $game
   *   The game which has just finished
   */
  public static function updatePlayerStatistics(Game $game) {
    $white_stats = static::loadForUser($game->getWhiteUser());
    $black_stats = static::loadForUser($game->getBlackUser());

    // Update games won, lost or drawn.
    switch ($game->getStatus()) {
      case GamePlay::STATUS_WHITE_WIN:
        $white_stats->setWon($white_stats->getWon() + 1);
        $black_stats->setLost($black_stats->getLost() + 1);
        break;
      case GamePlay::STATUS_BLACK_WIN:
        $white_stats->setLost($white_stats->getLost() + 1);
        $black_stats->setWon($black_stats->getWon() + 1);
        break;
      case GamePlay::STATUS_DRAW:
        $white_stats->setDrawn($white_stats->getDrawn() + 1);
        $black_stats->setDrawn($black_stats->getDrawn() + 1);
        break;
      default:
        $score = '';
    }
    $score = 0; // @todo...

    switch ($game->getStatus()) {
      case GamePlay::STATUS_WHITE_WIN:
      case GamePlay::STATUS_BLACK_WIN:
      case GamePlay::STATUS_DRAW:
        // Update number of current games.
        $white_stats->setCurrent($white_stats->getCurrent() - 1);
        $black_stats->setCurrent($black_stats->getCurrent() - 1);

        $white_stats->setPlayed($white_stats->getPlayed() + 1);
        $black_stats->setPlayed($black_stats->getPlayed() + 1);

        // Update rating change according to the winning probability
        $win_probability = Rating::calculateWinProbability($white_stats->getRating() - $black_stats->getRating());
        $rchange = round(Rating::calcRatingChangeMultiplier() * ($score - $win_probability));

        // Update rating.
        $white_stats->setRchanged($rchange);
        $black_stats->setRchanged(-$rchange);

        $white_stats->setRating($white_stats->getRating() + $rchange);
        $black_stats->setRating($black_stats->getRating() - $rchange);

        $white_stats->save();
        $black_stats->save();
        break;
    }
  }

}
