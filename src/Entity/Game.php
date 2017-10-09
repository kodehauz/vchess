<?php

namespace Drupal\vchess\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\gamer\Entity\GamerStatistics;
use Drupal\user\Entity\User;
use Drupal\user\UserInterface;
use Drupal\vchess\Game\GamePlay;

/**
 * @ContentEntityType(
 *   id = "vchess_game",
 *   label = @Translation("A vChess Game"),
 *   handlers = {
 *     "list_builder" = "\Drupal\vchess\GameListBuilder",
 *     "view_builder" = "\Drupal\vchess\GameViewBuilder",
 *     "form" = {
 *        "add" = "\Drupal\vchess\Form\OpponentGameForm",
 *        "edit" = "\Drupal\vchess\Form\PlayGameForm",
 *     },
 *     "access" = "Drupal\vchess\GameAccessControlHandler",
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\DefaultHtmlRouteProvider"
 *     },
 *   },
 *   base_table = "vchess_game",
 *   data_table = "vchess_game_field_data",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *     "uuid" = "uuid",
 *   },
 * )
 */
class Game extends ContentEntityBase {

  protected static $gameStatus = [
    GamePlay::STATUS_AWAITING_PLAYERS,
    GamePlay::STATUS_IN_PROGRESS,
    GamePlay::STATUS_DRAW,
    GamePlay::STATUS_WHITE_WIN,
    GamePlay::STATUS_BLACK_WIN,
  ];

  protected static $gameTime = [
    GamePlay::TIME_UNITS_DAYS,
    GamePlay::TIME_UNITS_HOURS,
    GamePlay::TIME_UNITS_MINS,
    GamePlay::TIME_UNITS_SECS
  ];

  /**
   * The game scoresheet which holds history of moves for this game.
   *
   * @var \Drupal\vchess\Entity\Scoresheet
   */
  protected $scoresheet;

  public function postSave(EntityStorageInterface $storage, $update = TRUE) {
    parent::postSave($storage, $update);
    // Save moves after this game is saved.
    // @todo Should we do integrity check on the board position?
    $this->getScoresheet()->saveMoves();
  }

  /**
   * Gets the last move for this game.
   */
  public function getLastMove() {
    return $this->getScoresheet()->getLastMove();
  }

  /**
   * Get the number of the current move.
   *
   * The move number will be the number of the move which is currently not yet complete.
   * Each move has a white move and a black move.
   *
   * i.e.
   * No moves, i.e.
   * 1. ... ...
   * move_no = 1 (i.e. waiting for move 1 of white)
   * After 1.e4 ...
   * move_no = 1 (i.e. waiting for move 1 of black)
   * After 1. e4 Nf6
   * move_no = 2 (i.e. waiting for move 2)
   */
  public function getMoveNumber() {
    return $this->getScoresheet()->getNextMoveNumber();
  }

  /**
   * See if the game is started yet
   *
   * @return
   *   TRUE if a move has already been made
   */
  public function isMoveMade() {
    // @todo: need to check this.
    return !($this->getMoveNumber() == 1 && $this->getTurn() === "w");
  }

  public function appendMove(Move $move) {
    $this->getScoresheet()->appendMove($move);
  }

  /**
   * Calculate time left in seconds till next move must be made
   *
   * @return
   *   Number of seconds till next move must be made
   */
  public function calculateTimeLeft() {
    // Convert time_per_move into seconds
    switch ($this->getTimeUnits()) {
      case GamePlay::TIME_UNITS_DAYS:
        $secs_per_unit = 24 * 60 * 60;
        break;
      case GamePlay::TIME_UNITS_HOURS:
        $secs_per_unit = 60 * 60;
        break;
      case GamePlay::TIME_UNITS_MINS:
        $secs_per_unit = 60;
        break;
      case GamePlay::TIME_UNITS_SECS:
        $secs_per_unit = 1;
        break;
      default:
        $secs_per_unit = 1;
        break;
    }

    $secs_per_move = $this->getTimePerMove() * $secs_per_unit;

    if ($this->status == GamePlay::STATUS_IN_PROGRESS) {
      $current_time = gmmktime();  // All dates are kept as GMT
      //        $other_time = gmdate("Y-m-d H:i:s");
      //        $just_time = time();
      //        drupal_set_message("current time:" . date("Y-m-d H:i:s", $current_time));
      //        drupal_set_message("other time:" . $other_time);
      //        drupal_set_message("time: " . date("Y-m-d H:i:s", $just_time));
      if ($this->isMoveMade()) {
        $move_time = strtotime($this->lastMove()->timestamp());
      }
      else {
        $move_time = strtotime($this->timeStarted());
      }
      $elapsed = $current_time - $move_time;
      $time_left = $secs_per_move - $elapsed;
    }
    else {
      $time_left = $secs_per_move;
    }

    return $time_left;
  }

  /**
   * Check if the game has been lost on time
   *
   * This checks if the time since the last move was made is
   * now more than the time allowed for the game
   *
   * @return
   *   TRUE if the game has been lost on time
   */
  public function isLostOnTime() {
    if ($this->calculateTimeLeft() <= 0) {
      $lost_on_time = TRUE;

      if ($this->getStatus() === static::STATUS_IN_PROGRESS) {
        $this->handleLostOnTime();
      }
    }
    else {
      $lost_on_time = FALSE;
    }

    return $lost_on_time;
  }

  /**
   * Say whether the game is over or not
   *
   * @return TRUE if the game is over
   */
  public function isGameOver() {
    return $this->getStatus() !== GamePlay::STATUS_IN_PROGRESS;
  }

  /**
   * Gets the color for the specified user in the current game.
   *
   * @param \Drupal\user\UserInterface $user
   *   The user for which the color is being sought.
   *
   * @return string
   *   'w' (white), 'b' (black) or '' (not in the game).
   */
  public function getPlayerColor(UserInterface $user) {
    if ($user->id() === $this->getWhiteUser()->id()) {
      return 'w';
    }
    else if ($user->id() === $this->getBlackUser()->id()) {
      return 'b';
    }
    else {
      return '';
    }
  }

  /**
   * See if the given user is one of the players.
   *
   * @param \Drupal\user\UserInterface $user
   *   The user to test.
   *
   * @return boolean
   *   TRUE if the given user is one of the players.
   */
  public function isUserPlaying(UserInterface $user) {
    return $this->getBlackUser()->id() == $user->id() || $this->getWhiteUser()->id() == $user->id();
  }

  /**
   * See if it's the given players move.
   *
   * @param \Drupal\user\UserInterface $user
   *   The user to test.
   *
   * @return boolean
   *   TRUE if the given user is one to move.
   */
  public function isPlayersMove(UserInterface $user) {
    return (($this->getTurn() === 'w' && $this->getWhiteUser()->id() === $user->id())
      || ($this->getTurn() === 'b' && $this->getBlackUser()->id() === $user->id()));
  }

  /**
   * @return \Drupal\vchess\Entity\Scoresheet
   *   The scoresheet for this game.
   */
  public function getScoresheet() {
    if (!isset($this->scoresheet)) {
      $this->scoresheet = new Scoresheet($this->id());
    }
    return $this->scoresheet;
  }

  /**
   * Gets the opponent for a particular player.
   *
   * @param \Drupal\user\UserInterface $user
   *   A user object corresponding to one of the players
   *
   * @return \Drupal\gamer\Entity\GamerStatistics $player
   *   The opposing player's game statistics.
   */
  public function getOpponent(UserInterface $user) {
    if ($this->getWhiteUser()->id() === $user->id()) {
      return GamerStatistics::loadForUser($this->getBlackUser());
    }
    else {
      return GamerStatistics::loadForUser($this->getWhiteUser());
    }
  }

  /**
   * Get the player who is the current challenger
   */
  public function getChallenger() {
    $uid = 0;

    if ($this->getWhiteUser() != NULL && $this->getBlackUser() == NULL) {
      $uid = $this->getWhiteUser()->id();
    }
    elseif ($this->getBlackUser() != NULL && $this->getWhiteUser() == NULL) {
      $uid = $this->getBlackUser()->id();
    }

    $challenger = GamerStatistics::loadForUser(User::load($uid));

    return $challenger;
  }

  /**
   * Get the game speed, which is the combination of the time_per_move
   * and the time_units, e.g. "3 days"
   *
   * @return
   *   Returns the speed per move, e.g. "3 days"
   */
  public function getSpeed() {
    return $this->getTimePerMove() . " " . $this->getTimeUnits();
  }

  public function getTurn() {
    return $this->get('turn')->value;
  }

  public function setTurn($value) {
    $this->set('turn', $value);
    return $this;
  }

  public function getStatus() {
    return $this->get('status')->value;
  }

  public function setStatus($value) {
    if (!in_array($value, static::$gameStatus)) {
      throw new \InvalidArgumentException('Value must be one of ' . implode(', ', static::$gameStatus));
    }
    $this->set('status', $value);
    return $this;
  }

  /**
   * @return \Drupal\user\UserInterface
   */
  public function getWhiteUser() {
    return $this->get('white_uid')->entity;
  }

  /**
   * Sets the white user.
   *
   * @param \Drupal\user\UserInterface $value
   *   The white user.
   *
   * @return $this
   *   For method chaining.
   */
  public function setWhiteUser(UserInterface $value) {
    $this->set('white_uid', $value);
    return $this;
  }

  /**
   * @return \Drupal\user\UserInterface
   */
  public function getBlackUser() {
    return $this->get('black_uid')->entity;
  }

  /**
   * Sets the black user.
   *
   * @param \Drupal\user\UserInterface $value
   *   The black user.
   *
   * @return $this
   */
  public function setBlackUser(UserInterface $value) {
    $this->set('black_uid', $value);
    return $this;
  }

  /**
   * @return string
   */
  public function getBoard() {
    return $this->get('board')->value;
  }

  /**
   * Sets the game board.
   *
   * @param string $value
   *
   * @return $this
   */
  public function setBoard($value) {
    $this->set('board', $value);
    return $this;
  }

  public function getCastling() {
    return $this->get('castling')->value;
  }

  /**
   * Create $castling string.
   *
   * @param string $value
   * The value of the castling. If neither side can castle, this is "-".
   * Otherwise, this has one or more letters:
   * - "K" (White can castle kingside),
   * - "Q" (White can castle queenside),
   * - "k" (Black can castle kingside), and/or
   * - "q" (Black can castle queenside)
   * e.g. "KQkq"
   *
   * @return $this
   *  For method chaining.
   *
   * @see http://en.wikipedia.org/wiki/Forsyth%E2%80%93Edwards_Notation
   */
  public function setCastling($value) {
    $this->set('castling', $value);
    return $this;
  }

  public function getEnPassantSquare() {
    return $this->get('en_passant_square')->value;
  }

  public function setEnPassantSquare($value) {
    $this->set('en_passant_square', $value);
    return $this;
  }

  public function getTimePerMove() {
    return $this->get('time_per_move')->value;
  }

  /**
   * Sets the time per move
   *
   * This just sets the value of the time per move (e.g. 1 or 3).  The units of time
   * would be set in set_time_units(), which isn't currently needed so does not exist.
   *
   * @param  $value
   *   Time per move, e.g. "3".
   *
   * @return $this
   */
  public function setTimePerMove($value) {
    $this->set('time_per_move', $value);
    return $this;
  }

  public function getTimeUnits() {
    return $this->get('time_units')->value;
  }

  public function setTimeUnits($value) {
    if (!in_array($value, static::$gameTime)) {
      throw new \InvalidArgumentException('Value must be one of ' . implode(', ', static::$gameTime));
    }
    $this->set('time_units', $value);
    return $this;
  }

  public function getTimeStarted() {
    return $this->get('time_started')->value;
  }

  public function setTimeStarted($value) {
    $this->set('time_started', $value);
    return $this;
  }

  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);
    // Table of each game, one row per game
    $fields['turn'] = BaseFieldDefinition::create('string')
      ->setDescription(t('Whose turn it is to play, either "w" (white) or "b" (black)'))
      ->setRequired(TRUE)
      ->setDefaultValue('w')
      ->addConstraint('AllowedValues', ['choices' => ['w', 'b']]);

    $fields['status'] = BaseFieldDefinition::create('string')
      ->setDescription(t('Status of the game'))
      ->setDefaultValue('in progress')
      ->setRequired(TRUE);

    $fields['white_uid'] = BaseFieldDefinition::create('entity_reference')
      ->setDescription(t('Userid of white player'))
      ->setSetting('target_type', 'user');

    $fields['black_uid'] = BaseFieldDefinition::create('entity_reference')
      ->setDescription(t('Userid of black player'))
      ->setSetting('target_type', 'user');

    $fields['board']  = BaseFieldDefinition::create('string')
      ->setDescription(t('The board position saved as standard Forsyth�Edwards Notation (FEN)'))
      ->setDefaultValue('rnbqkbnr/pppppppp/8/8/8/8/PPPPPPPP/RNBQKBNR')
      ->setRequired(TRUE);

    $fields['castling'] = BaseFieldDefinition::create('string')
      ->setDescription(t('Castling availability. If neither side can castle, this is "-". Otherwise, this has one or more letters: "K" (White can castle kingside), "Q" (White can castle queenside), "k" (Black can castle kingside), and/or "q" (Black can castle queenside).'))
      ->setDefaultValue('KQkq') ;

    $fields['en_passant_square'] = BaseFieldDefinition::create('string')
      ->setDescription(t('En passant target square. If there is no en passant target square, this is "-". If a pawn has just made a 2-square move, this is the position "behind" the pawn. This is recorded regardless of whether there is a pawn in position to make an en-passant capture.'))
      ->setDefaultValue('-');

    $fields['time_per_move'] = BaseFieldDefinition::create('integer')
      ->setDescription(t('Time per move (the units are defined by time_units field)'))
      ->setDefaultValue(DEFAULT_TIME_PER_MOVE);

    $fields['time_units'] = BaseFieldDefinition::create('string')
      ->setDescription(t('Units of the time_per_move field'))
      ->setDefaultValue(DEFAULT_TIME_UNITS);

    $fields['time_started'] = BaseFieldDefinition::create('timestamp')
      ->setDescription(t('Date and time of the start of the game, e.g. 2012-05-03 12:01:29'));

    return $fields;
  }


  /**
   * @file
   * Functions for dealing with player statistics
   */

  /**
   * Calculate the number of games won
   */
  public static function gamesWonByUser($uid) {
    $query = \Drupal::entityTypeManager()->getStorage('vchess_game')->getAggregateQuery('OR');
    $white_condition = $query->andConditionGroup()
      ->condition('white_uid', $uid)
      ->condition('status', GamePlay::STATUS_WHITE_WIN);
    $black_condition = $query->andConditionGroup()
      ->condition('black_uid', $uid)
      ->condition('status', GamePlay::STATUS_BLACK_WIN);

    $count = $query
      ->aggregate('gid', 'COUNT')
      ->condition($white_condition)
      ->condition($black_condition)
      ->execute()
      ->fetchColumn();

//    $sql = "SELECT count(gid) FROM {vchess_games} WHERE " .
//      "(white_uid = '" . $uid . "' AND status = '" . STATUS_WHITE_WIN . ") " .
//      "OR";
//    "(black_uid = '" . $uid . "' AND status = '" . STATUS_BLACK_WIN . ") ";

//    $result = ($sql);
//    $count = $result->fetchColumn();

    return $count;
  }

  /**
   * Calculate the number of games lost
   */
  public static function gamesLostByUser($uid) {
    $query = \Drupal::entityTypeManager()->getStorage('vchess_game')->getAggregateQuery('OR');
    $white_condition = $query->andConditionGroup()
      ->condition('white_uid', $uid)
      ->condition('status', GamePlay::STATUS_BLACK_WIN);
    $black_condition = $query->andConditionGroup()
      ->condition('black_uid', $uid)
      ->condition('status', GamePlay::STATUS_WHITE_WIN);

    $count = $query
      ->condition($white_condition)
      ->condition($black_condition)
      ->aggregate('gid', 'COUNT')
      ->execute()
      ->fetchColumn();

//    $sql = "SELECT count(gid) FROM {vchess_games} WHERE " .
//      "(white_uid = '" . $uid . "' AND status = '" . STATUS_BLACK_WIN . ") " .
//      "OR";
//    "(black_uid = '" . $uid . "' AND status = '" . STATUS_WHITE_WIN . ") ";
//
//    $result = db_query($sql);
//    $count = $result->fetchColumn();

    return $count;
  }

  /**
   * Calculate the number of games in progress for a given user.
   *
   * @param \Drupal\user\UserInterface $user
   *   The user.
   *
   * @return int
   *   The number of games currently being played by a user.
   */
  public static function countUsersCurrentGames(UserInterface $user) {
    $query = \Drupal::entityTypeManager()->getStorage('vchess_game')->getAggregateQuery('AND');
    $user_condition = $query->orConditionGroup()
      ->condition('white_uid', $user->id())
      ->condition('black_uid', $user->id());

    $count = $query
      ->condition($user_condition)
      ->condition('status', GamePlay::STATUS_IN_PROGRESS)
      ->aggregate('id', 'COUNT')
      ->execute();

    if ($count) {
      return $count[0]['id_count'];
    }
    else {
      return 0;
    }
  }


  /**
   * Load a list of games for the given userid
   *
   * @param
   *   $uid the userid of the user whose games we want
   *
   * @return
   *   An array of in progress games
   */
  public static function loadUsersCurrentGames(UserInterface $user) {
//    $game_list = array();
    $query = \Drupal::entityTypeManager()->getStorage('vchess_game')->getQuery('AND');
    $user_condition = $query->orConditionGroup()
      ->condition('white_uid', $user->id())
      ->condition('black_uid', $user->id());

    $ids = $query
      ->condition($user_condition)
      ->condition('status', 'in progress')
      ->sort('time_started', 'DESC')
      ->execute();
//
//    $sql = "SELECT gid FROM {vchess_games} WHERE (white_uid = :uid OR black_uid = :uid) AND status = 'in progress'";
//    $result = db_query($sql, array('uid' => $uid));
    $game_list = Game::loadMultiple($ids);
//
//    foreach ($result as $data) {
//      $gid = $data->gid;
//
//      // Add a game to the list
//      $game = new Game();
//      $game->load($gid);
//      $game_list[] = $game;
//    }

    // NB: sort will destroy index key, therefore $game_list['gid'] is used
    // later instead.
//    if (count($game_list) > 0) {
//      usort($game_list, 'vchess_compareTimestamp');
//    }
    return $game_list;
  }

  /**
   * Load a list of all current games
   *
   * @return \Drupal\vchess\Entity\Game[]
   *   An array of current (in progress) games
   */
  public static function loadAllCurrentGames() {
//    $game_list = array();
    $query = \Drupal::entityTypeManager()->getStorage('vchess_game')->getQuery();

    $ids = $query
      ->condition('status', GamePlay::STATUS_IN_PROGRESS)
      ->sort('time_started', 'DESC')
      ->execute();
//    $sql = "SELECT gid FROM {vchess_games} WHERE status = '" . STATUS_IN_PROGRESS . "'";
//    $result = db_query($sql);

    $game_list = Game::loadMultiple($ids);
//    foreach ($result as $data) {
//      $gid = $data->gid;
//
//      // Add a game to the list
//      $game = new Game();
//      $game->load($gid);
//      $game_list[] = $game;
//    }

    // NB: sort will destroy index key, therefore $game_list['gid'] is used
    // later instead.
//    if (count($game_list) > 0) {
//      usort($game_list, 'vchess_compareTimestamp');
//    }
    return $game_list;
  }

  /**
   * Load a list of all current games
   *
   * @return \Drupal\vchess\Entity\Game[]
   *   An array of current (in progress) games
   */
  public static function loadChallenges() {
//    $game_list = array();
    $query = \Drupal::entityTypeManager()->getStorage('vchess_game')->getQuery();

    $ids = $query
      ->condition('status', GamePlay::STATUS_AWAITING_PLAYERS)
      ->sort('time_started', 'ASC')
      ->execute();
//
//    $sql = "SELECT gid FROM {vchess_games} WHERE status = '" . STATUS_AWAITING_PLAYERS . "'" .
//      "ORDER BY time_started ASC";
//    $result = db_query($sql);

    $game_list = Game::loadMultiple($ids);
//
//    foreach ($result as $data) {
//      $gid = $data->gid;
//
//      // Add a game to the list
//      $game = new Game();
//      $game->load($gid);
//      $game_list[] = $game;
//    }

    // NB: sort will destroy index key, therefore $game_list['gid'] is used
    // later instead.
//    if (count($game_list) > 0) {
//      usort($game_list, 'vchess_compareTimestamp');
//    }
    return $game_list;
  }
}
