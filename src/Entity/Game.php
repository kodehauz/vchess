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
 *   label = @Translation("VChess game"),
 *   handlers = {
 *     "list_builder" = "\Drupal\vchess\GameListBuilder",
 *     "views_data" = "Drupal\vchess\Views\GameViewsData",
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

  /**
   * Gets the last move for this game.
   *
   * @return \Drupal\vchess\Entity\Move
   */
  public function getLastMove() {
    return $this->getScoresheet()->getLastMove();
  }

  /**
   * Gets the number of the next move to be played.
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
   *
   * @return int
   */
  public function getMoveNumber() {
    return $this->getScoresheet()->getNextMoveNumber();
  }

  /**
   * Checks if the game is started yet.
   *
   * @return bool
   *   TRUE if a move has already been made
   */
  public function isMoveMade() {
    // @todo: need to check this.
    return !($this->getMoveNumber() === 1 && $this->getTurn() === 'w');
  }

  /**
   * @return $this
   */
  public function appendMove(Move $move) {
    $this->getScoresheet()->appendMove($move);
    return $this;
  }

  /**
   * Calculates time left in seconds till next move must be made.
   *
   * @return int
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

    if ($this->status === GamePlay::STATUS_IN_PROGRESS) {
      // All dates are kept as GMT
      $current_time = time();
      //        $other_time = gmdate("Y-m-d H:i:s");
      //        $just_time = time();
      //        drupal_set_message("current time:" . date("Y-m-d H:i:s", $current_time));
      //        drupal_set_message("other time:" . $other_time);
      //        drupal_set_message("time: " . date("Y-m-d H:i:s", $just_time));
      if ($this->isMoveMade()) {
        $move_time = strtotime($this->getLastMove()->getTimestamp());
      }
      else {
        $move_time = strtotime($this->getTimeStarted());
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
    if ($this->getWhiteUser() && $user->id() === $this->getWhiteUser()->id()) {
      return 'w';
    }
    if ($this->getBlackUser() && $user->id() === $this->getBlackUser()->id()) {
      return 'b';
    }
    return '';
  }

  /**
   * Checks if the given user is one of the players.
   *
   * @param \Drupal\user\UserInterface $user
   *   The user to test.
   *
   * @return boolean
   *   TRUE if the given user is one of the players.
   */
  public function isUserPlaying(UserInterface $user) {
    return $this->getPlayerColor($user) !== '';
  }

  /**
   * Checks if it is the given players move.
   *
   * @param \Drupal\user\UserInterface $user
   *   The user to test.
   *
   * @return boolean
   *   TRUE if the given user is one to move.
   */
  public function isPlayersMove(UserInterface $user) {
    $player_color = $this->getPlayerColor($user);
    return $player_color !== '' && $player_color === $this->getTurn();
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
   * @return \Drupal\gamer\Entity\GamerStatistics|null $player
   *   The opposing player's game statistics.
   */
  public function getOpponent(UserInterface $user) {
    $player_color = $this->getPlayerColor($user);
    if ($player_color === 'w') {
      return GamerStatistics::loadForUser($this->getBlackUser());
    }

    if ($player_color === 'b') {
      return GamerStatistics::loadForUser($this->getWhiteUser());
    }

    return NULL;
  }

  /**
   * Sets the player who initiated the challenge.
   *
   * @return $this
   */
  public function setChallenger(UserInterface $user) {
    assert($this->isUserPlaying($user));
    $this->set('challenger', $this->getPlayerColor($user));
    return $this;
  }

  /**
   * Gets the player who is the current challenger.
   *
   * @return \Drupal\user\UserInterface
   */
  public function getChallenger() {
    if ($this->getWhiteUser() !== NULL && $this->getBlackUser() === NULL) {
      return $this->getWhiteUser();
    }
    else if ($this->getBlackUser() !== NULL && $this->getWhiteUser() === NULL) {
      return $this->getBlackUser();
    }
    else {
      return $this->get('challenger') === 'w' ? $this->getWhiteUser() : $this->getBlackUser();
    }
  }

  /**
   * Sets a single user randomly to either be the black or white player.
   *
   * If the white or black player is already set, then the remaining slot is
   * taken. If the two players are already set, then do nothing and return false.
   *
   * Note that it is an error to set a player when there are already 2 players
   * assigned to the game.
   *
   * @param \Drupal\user\UserInterface $user
   *   The user to be randomly set to either black or white.
   *
   * @return $this
   *   For method chaining.
   */
  public function setPlayerRandomly($user) {
    if ($this->getWhiteUser() === NULL && $this->getBlackUser() === NULL) {
      if (mt_rand(1,100) < 50) {
        $this->setWhiteUser($user);
      }
      else {
        $this->setBlackUser($user);
      }
    }
    else if ($this->getWhiteUser() === NULL) {
      $this->setWhiteUser($user);
    }
    else if ($this->getBlackUser() === NULL) {
      $this->setBlackUser($user);
    }
    else {
      \Drupal::logger('VChess')
        ->error(t( "Attempt to set a player when both players are already assigned"));
    }
    return $this;
  }

  /**
   * Sets the players for a new game.
   *
   * It is at this stage that the game really begins playing.
   *
   * @param UserInterface $white_user
   *   White player user entity.
   * @param UserInterface $black_user
   *   Black player user entity.
   *
   * @return $this.
   */
  public function setPlayers(UserInterface $white_user, UserInterface $black_user) {
    $this
      ->setWhiteUser($white_user)
      ->setBlackUser($black_user);
    return $this;
  }

  /**
   * Sets the human-readable title / label of the game.
   *
   * @param string $value
   *   The label of the game.
   *
   * @return $this
   */
  public function setLabel($value) {
    $this->set('label', $value);
    return $this;
  }

  /**
   * Gets the game speed, which is the combination of the time_per_move
   * and the time_units, e.g. "3 days"
   *
   * @return string
   *   Returns the speed per move, e.g. "3 days"
   */
  public function getSpeed() {
    return $this->getTimePerMove() . ' ' . $this->getTimeUnits();
  }

  /**
   * @return string
   */
  public function getTurn() {
    return $this->get('turn')->value;
  }

  /**
   * @return $this
   */
  public function setTurn($value) {
    $this->set('turn', $value);
    return $this;
  }

  /**
   * @return string
   */
  public function getStatus() {
    return $this->get('status')->value;
  }

  /**
   * @return $this
   */
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

  /**
   * @return string
   */
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

  /**
   * @return string
   */
  public function getEnPassantSquare() {
    return $this->get('en_passant_square')->value;
  }

  /**
   * @return $this
   */
  public function setEnPassantSquare($value) {
    $this->set('en_passant_square', $value);
    return $this;
  }

  /**
   * @return int
   */
  public function getTimePerMove() {
    return $this->get('time_per_move')->value;
  }

  /**
   * Sets the time per move
   *
   * This just sets the value of the time per move (e.g. 1 or 3).  The units of time
   * would be set in setTimeUnits(), which isn't currently needed so does not exist.
   *
   * @param int $value
   *   Time per move, e.g. 3.
   *
   * @return $this
   *
   * @see \Drupal\vchess\Entity\Game::setTimePerMove()
   */
  public function setTimePerMove($value) {
    $this->set('time_per_move', $value);
    return $this;
  }

  /**
   * @return string
   */
  public function getTimeUnits() {
    return $this->get('time_units')->value;
  }

  /**
   * @return $this
   */
  public function setTimeUnits($value) {
    if (!in_array($value, static::$gameTime, TRUE)) {
      throw new \InvalidArgumentException('Value must be one of ' . implode(', ', static::$gameTime));
    }
    $this->set('time_units', $value);
    return $this;
  }

  /**
   * @return int
   */
  public function getTimeStarted() {
    return $this->get('time_started')->value;
  }

  /**
   * @return $this
   */
  public function setTimeStarted($value) {
    $this->set('time_started', $value);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);
    // Table of each game, one row per game
    $fields['turn'] = BaseFieldDefinition::create('string')
      ->setLabel('Turn')
      ->setDescription(t('Whose turn it is to play, either "w" (white) or "b" (black)'))
      ->setRequired(TRUE)
      ->setDefaultValue('w')
      ->setSetting('max_length', 1)
      ->addConstraint('AllowedValues', ['choices' => ['w', 'b']]);

    $fields['status'] = BaseFieldDefinition::create('string')
      ->setLabel('Status')
      ->setDescription(t('Status of the game'))
      ->setSetting('max_length', 64)
      ->setDefaultValue(GamePlay::STATUS_AWAITING_PLAYERS)
      ->setRequired(TRUE);

    $fields['white_uid'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel('White player')
      ->setDescription(t('User ID of white player'))
      ->setSetting('target_type', 'user');

    $fields['black_uid'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel('Black player')
      ->setDescription(t('User ID of black player'))
      ->setSetting('target_type', 'user');

    $fields['challenger'] = BaseFieldDefinition::create('string')
      ->setLabel('Challenger')
      ->setDescription(t('The color of the player who initiated the challenge'))
      ->setSetting('max_length', 2)
      ->addConstraint('AllowedValues', ['choices' => ['w', 'b']])
      ->setRequired(TRUE);

    $fields['label'] = BaseFieldDefinition::create('string')
      ->setLabel('Label')
      ->setDescription(t('A descriptive label for this game'))
      ->setRequired(FALSE);

    $fields['board']  = BaseFieldDefinition::create('string')
      ->setLabel('Board')
      ->setDescription(t('The board position saved as standard Forsyth�Edwards Notation (FEN)'))
      ->setDefaultValue('rnbqkbnr/pppppppp/8/8/8/8/PPPPPPPP/RNBQKBNR')
      ->setSetting('max_length', 128)
      ->setRequired(TRUE);

    $fields['castling'] = BaseFieldDefinition::create('string')
      ->setLabel('Castling')
      ->setDescription(t('Castling availability. If neither side can castle, this is "-". Otherwise, this has one or more letters: "K" (White can castle kingside), "Q" (White can castle queenside), "k" (Black can castle kingside), and/or "q" (Black can castle queenside).'))
      ->setSetting('max_length', 5)
      ->setDefaultValue('KQkq') ;

    $fields['en_passant_square'] = BaseFieldDefinition::create('string')
      ->setLabel('En passant square')
      ->setDescription(t('En passant target square. If there is no en passant target square, this is "-". If a pawn has just made a 2-square move, this is the position "behind" the pawn. This is recorded regardless of whether there is a pawn in position to make an en-passant capture.'))
      ->setSetting('max_length', 2)
      ->setDefaultValue('-');

    $fields['time_per_move'] = BaseFieldDefinition::create('integer')
      ->setLabel('Time per move')
      ->setDescription(t('Time per move (the units are defined by time_units field)'))
      ->setDefaultValue(DEFAULT_TIME_PER_MOVE);

    $fields['time_units'] = BaseFieldDefinition::create('string')
      ->setLabel('Time units')
      ->setDescription(t('Units of the time_per_move field'))
      ->setSetting('max_length', 10)
      ->setDefaultValue(DEFAULT_TIME_UNITS);

    $fields['time_started'] = BaseFieldDefinition::create('timestamp')
      ->setLabel('Time started')
      ->setDescription(t('Date and time of the start of the game, e.g. 2012-05-03 12:01:29'));

    return $fields;
  }
  /**
   * Deals with the case that the player has lost on time.
   *
   * @return $this
   */
  protected function handleLostOnTime() {
    if ($this->getTurn() === 'w') {
      $this->setStatus(GamePlay::STATUS_BLACK_WIN)->save();
    }
    else {
      $this->setStatus(GamePlay::STATUS_WHITE_WIN)->save();
    }
    return $this;
  }

  /**
   * Checks if the game has been lost on time.
   *
   * This checks if the time since the last move was made is
   * now more than the time allowed for the game
   *
   * @return bool
   *   TRUE if the game has been lost on time.
   */
  public function isLostOnTime() {
    if ($this->calculateTimeLeft() <= 0) {
      $lost_on_time = TRUE;

      if ($this->getStatus() === GamePlay::STATUS_IN_PROGRESS) {
        $this->handleLostOnTime();
      }
    }
    else {
      $lost_on_time = FALSE;
    }

    return $lost_on_time;
  }


  /**
   * {@inheritdoc}
   */
  public function preSave(EntityStorageInterface $storage) {
    if (empty($this->label())) {
      // If label has not been set, then use the players' names.
      if ($white = $this->getWhiteUser()) {
        $white_name = $white->getDisplayName();
      }
      else {
        $white_name = 'Unknown';
      }
      if ($white = $this->getBlackUser()) {
        $black_name = $white->getDisplayName();
      }
      else {
        $black_name = 'Unknown';
      }
      $this->setLabel($white_name . ' vs. ' . $black_name);
    }

    // Ensure that games without complete users are marked as awaiting players.
    // To avoid fails.
    if ($this->getBlackUser() === NULL || $this->getWhiteUser() === NULL) {
      $this->set('status', GamePlay::STATUS_AWAITING_PLAYERS);
    }
    parent::preSave($storage);
  }

  /**
   * {@inheritdoc}
   */
  public function postSave(EntityStorageInterface $storage, $update = TRUE) {
    parent::postSave($storage, $update);
    // Save moves after this game is saved.
    // @todo Should we do integrity check on the board position?
    $this->getScoresheet()->saveMoves();
  }

  /**
   * Counts the number of games won by a given user.
   *
   * @param \Drupal\user\UserInterface $user
   *   The user whose lost games are being counted.
   *
   * @return integer
   */
  public static function countGamesWonByUser(UserInterface $user) {
    $query = \Drupal::entityTypeManager()->getStorage('vchess_game')->getAggregateQuery('OR');
    $white_condition = $query->andConditionGroup()
      ->condition('white_uid', $user->id())
      ->condition('status', GamePlay::STATUS_WHITE_WIN);
    $black_condition = $query->andConditionGroup()
      ->condition('black_uid', $user->id())
      ->condition('status', GamePlay::STATUS_BLACK_WIN);

    $count = $query
      ->aggregate('id', 'COUNT')
      ->condition($white_condition)
      ->condition($black_condition)
      ->execute();

    if ($count) {
      return $count[0]['id_count'];
    }
    return 0;
  }

  /**
   * Counts the number of games lost by a user.
   *
   * @param \Drupal\user\UserInterface $user
   *   The user whose lost games are being counted.
   *
   * @return integer
   */
  public static function countGamesLostByUser(UserInterface $user) {
    $query = \Drupal::entityTypeManager()->getStorage('vchess_game')->getAggregateQuery('OR');
    $white_condition = $query->andConditionGroup()
      ->condition('white_uid', $user->id())
      ->condition('status', GamePlay::STATUS_BLACK_WIN);
    $black_condition = $query->andConditionGroup()
      ->condition('black_uid', $user->id())
      ->condition('status', GamePlay::STATUS_WHITE_WIN);

    $count = $query
      ->condition($white_condition)
      ->condition($black_condition)
      ->aggregate('id', 'COUNT')
      ->execute();

    if ($count) {
      return $count[0]['id_count'];
    }
    return 0;
  }

  /**
   * Calculates the number of games in progress for a given user.
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
    return 0;
  }

  /**
   * Loads a list of games for the given user.
   *
   * @param UserInterface $user
   *   The user whose games we want.
   *
   * @return \Drupal\vchess\Entity\Game[]
   *   An array of the user's in progress games.
   */
  public static function loadUsersCurrentGames(UserInterface $user) {
    $query = \Drupal::entityTypeManager()->getStorage('vchess_game')->getQuery('AND');
    $user_condition = $query->orConditionGroup()
      ->condition('white_uid', $user->id())
      ->condition('black_uid', $user->id());

    $ids = $query
      ->condition($user_condition)
      ->condition('status', GamePlay::STATUS_IN_PROGRESS)
      ->sort('time_started', 'DESC')
      ->execute();

    return static::loadMultiple($ids);
  }

  /**
   * Loads a list of all current games.
   *
   * @return \Drupal\vchess\Entity\Game[]
   *   An array of all current (in progress) games
   */
  public static function loadAllCurrentGames() {
    $query = \Drupal::entityTypeManager()->getStorage('vchess_game')->getQuery();
    $ids = $query
      ->condition('status', GamePlay::STATUS_IN_PROGRESS)
      ->sort('time_started', 'DESC')
      ->execute();
    return static::loadMultiple($ids);
  }

  /**
   * Loads a list of all challenges for a particular user or all users.
   *
   * @param \Drupal\user\UserInterface|null $user
   *   The user for which challenges are to be loaded or null to load for all
   *   users.
   * @return \Drupal\vchess\Entity\Game[]
   *   An array of challenges awaiting players.
   */
  public static function loadChallenges(UserInterface $user = NULL) {
    $query = \Drupal::entityTypeManager()->getStorage('vchess_game')->getQuery();
    $query
      ->condition('status', GamePlay::STATUS_AWAITING_PLAYERS)
      ->sort('time_started', 'ASC');

    if ($user) {
      $user_condition = $query->orConditionGroup()
        ->condition('white_uid', $user->id())
        ->condition('black_uid', $user->id());
      $query->condition($user_condition);
    }

    return static::loadMultiple($query->execute());
  }

  /**
   * Loads a list of all challenges not raised by this user.
   *
   * A challenge is a game without complete players.
   *
   * @param \Drupal\user\UserInterface $user
   *   The user to be excluded from the listing of challenges.
   *
   * @return \Drupal\vchess\Entity\Game[]
   *   An array of challenges awaiting players.
   */
  public static function loadChallengesWithout(UserInterface $user) {
    $query = \Drupal::entityTypeManager()->getStorage('vchess_game')->getQuery();
    $user_condition = $query->orConditionGroup()
      ->condition('white_uid', $user->id(), '<>')
      ->condition('black_uid', $user->id(), '<>');
    $ids = $query
      ->condition('status', GamePlay::STATUS_AWAITING_PLAYERS)
      ->condition($user_condition)
      ->sort('time_started', 'ASC')
      ->execute();
    return static::loadMultiple($ids);
  }

  /**
   * Calculates and returns a user's game streak.
   *
   * @param \Drupal\user\UserInterface $user
   *   The user who's game playing streak is to be returned.
   * @param int $limit
   *   (optional) The number of games to check. The return value will be less if
   *   the number of games played by the user is not up to the specified limit.
   *   Defaults to 10 games.
   *
   * @return string[]
   *   An array containing the user's streak (strings 'W', 'L' and 'D' for 'win',
   *   'lose' and 'draw') starting from the most recent game.
   *   The array is keyed by the game IDs. E.g.
   *   ['10' => 'L', '9' => 'L', '8' => 'W', '7' => 'D', '6' => 'W', '5' => 'W',
   *    '4' => 'W', '3' => 'L', '2' => 'D', '1' => 'D']
   */
  public static function getPlayerStreak(UserInterface $user, $limit = NULL) {
    if ($limit === NULL) {
      $limit = 10;
    }
    $query = \Drupal::entityTypeManager()
      ->getStorage('vchess_game')
      ->getQuery();
    $user_condition = $query->orConditionGroup()
      ->condition('white_uid', $user->id())
      ->condition('black_uid', $user->id());

    $status_condition = $query->orConditionGroup()
      ->condition('status', GamePlay::STATUS_WHITE_WIN)
      ->condition('status', GamePlay::STATUS_BLACK_WIN)
      ->condition('status', GamePlay::STATUS_DRAW);

    $ids = $query
      ->condition($user_condition)
      ->condition($status_condition)
      ->sort('time_started', 'DESC')
      ->range(0, $limit)
      ->execute();

    $streak = [];
    /** @var \Drupal\vchess\Entity\Game[] $games */
    $games = static::loadMultiple($ids);
    foreach ($games as $id => $game) {
      $status = $game->getStatus();
      $user_color = $game->getPlayerColor($user);
      if ($status === GamePlay::STATUS_DRAW) {
        $streak[$id] = 'D';
      }
      elseif (($status === GamePlay::STATUS_WHITE_WIN && $user_color === 'b')
        || ($status === GamePlay::STATUS_BLACK_WIN && $user_color === 'w')) {
        $streak[$id] = 'L';
      }
      elseif (($status === GamePlay::STATUS_WHITE_WIN && $user_color === 'w')
        || ($status === GamePlay::STATUS_BLACK_WIN && $user_color === 'b')) {
        $streak[$id] = 'W';
      }
    }
    return $streak;
  }

}
