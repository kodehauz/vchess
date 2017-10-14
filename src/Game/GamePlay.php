<?php

namespace Drupal\vchess\Game;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\gamer\Entity\GamerStatistics;
use Drupal\user\UserInterface;
use Drupal\vchess\Entity\Game;
use Drupal\vchess\Entity\Move;

class GamePlay {

  // Define game statuses
  const STATUS_WHITE_WIN = '1-0';
  const STATUS_BLACK_WIN = '0-1';
  const STATUS_DRAW = '1/2-1/2';
  const STATUS_IN_PROGRESS = 'in progress';
  const STATUS_AWAITING_PLAYERS = 'awaiting players';

    // Define time units
  const TIME_UNITS_DAYS = 'days';
  const TIME_UNITS_HOURS = 'hours';
  const TIME_UNITS_MINS = 'mins';
  const TIME_UNITS_SECS = 'secs';

    // Define errors
  const ERROR_CASTLING_SQUARES_BLOCKED = 'ERROR: Castling squares are blocked!';
  const ERROR_CANNOT_ESCAPE_CHECK_BY_CASTLING = 'ERROR: You cannot escape check by castling!';
  const ERROR_CANNOT_CASTLE_ACROSS_CHECK = 'ERROR: You cannot castle across check!';
  const ERROR_CANNOT_CASTLE_SHORT = 'ERROR: You cannot castle short anymore!';
  const ERROR_CANNOT_CASTLE_LONG = 'ERROR: You cannot castle long anymore!';


  /**
   * The entity holding game information.
   *
   * @var \Drupal\vchess\Entity\Game
   */
  protected $game;

  /**
   * The last move made just before this one.
   *
   * @var string
   */
  protected $lastMove;

  /**
   * The game board.
   *
   * @var \Drupal\vchess\Game\Board
   */
  protected $board;

  protected $time_per_move;  // e.g. 3
  protected $time_units;     // e.g. TIME_UNITS_DAYS

  /**
   * Maintains track of the castling status in the game.
   *
   * @var string[][]
   */
  protected $castling = [];
  
  // chatter: list of chatter lines (first is newest)
  
  // Dynamic (based on user id) entries:
  // p_maymove: whether it's player's turn (always 0 if user is not playing)
  // p_mayundo: player may undo last move
  // p_mayabort: player may abort game (first move or opponent took too long)
  // p_mayarchive: player may move game to archive
  
  /**
   * Game constructor
   */
  function __construct(Game $game = NULL) {
    if ($game === NULL) {
      // Setup the board
      $this->board = new Board();
      $this->board
        ->setupAsStandard()
        ->resetEnPassantSquare();

      // Initialize the game entity.
      $this->game = Game::create([
        'turn' => 'w',
        'castling' => 'KQkq',
        'status' => static::STATUS_AWAITING_PLAYERS,
        'time_per_move' => DEFAULT_TIME_PER_MOVE,
        'time_units' => DEFAULT_TIME_UNITS,
        'board' => $this->board->getFenString(),
      ]);
    }
    else {
      $this->game = $game;
      $this->board = new Board();
      $this->board
        ->setupPosition($game->getBoard())
        ->setEnPassantSquare($game->getEnPassantSquare());
      $this
        ->setCastling('w', 'Q', strpos($game->getCastling(), 'Q') !== FALSE)
        ->setCastling('w', 'K', strpos($game->getCastling(), 'K') !== FALSE)
        ->setCastling('b', 'Q', strpos($game->getCastling(), 'q') !== FALSE)
        ->setCastling('b', 'K', strpos($game->getCastling(), 'k') !== FALSE);
    }
  }
  
  /**
   * Get the timestamp of when the game started
   *
   * @return string|false
   *   A timestamp, e.g. "2012-05-03 12:01:29", false if the game has not yet
   *  started
   */
  public function getTimeStarted() {
    if ($this->game->getStatus() === static::STATUS_IN_PROGRESS) {
      return $this->game->getTimeStarted();
    }
    else {
      return FALSE;
    }
  }

  /**
   * Deal with the case that the player has lost on time
   */
  protected function handleLostOnTime() {
    if ($this->game->getTurn() == 'w') {
      $this->game->setStatus(static::STATUS_BLACK_WIN)->save();
    }
    else {
      $this->game->setStatus(static::STATUS_WHITE_WIN)->save();
    }
  }

  /**
   * Set the players for a new game.
   *
   * It is at this stage that the game really begins playing.
   *
   * @param UserInterface $white_user
   *   White player user entity.
   * @param UserInterface $black_user
   *   Black player user entity.
   *
   * @return int
   *   Game id, $gid.
   */
  public function setPlayers(UserInterface $white_user, UserInterface $black_user) {
    // Build new game context
    $this->game
      ->setWhiteUser($white_user)
      ->setBlackUser($black_user)
      ->setStatus(static::STATUS_IN_PROGRESS)
      ->save();
    return $this->game->id();
  }

  /**
   * Set a single player.
   *
   * Note that it is an error to set a player when there are already 2 players
   * assigned to the game
   */
  public function setPlayer(UserInterface $user) {
//     watchdog("VChess", "In game.inc for game %gid, at start of set_player() setting player uid=%uid." .
//         " Currently white_uid=%white_uid and black_uid=%black_uid",
//         array('%gid' => $this->gid(),
//             '%uid' => $uid,
//             '%white_uid' => $this->white_uid,
//             '%black_uid' => $this->black_uid));

    if ($this->game->getWhiteUser() === NULL && $this->game->getBlackUser() === NULL) {
      if (rand(1, 100) < 50) {
        $this->game
          ->setWhiteUser($user)
          ->save();
      }
      else {
        $this->game
          ->setBlackUser($user)
          ->save();
      }
    }
    else {
      if ($this->game->getWhiteUser() == NULL) {
        $this->game
          ->setWhiteUser($user)
          ->setStatus(static::STATUS_IN_PROGRESS)
          ->save();
      }
      elseif ($this->game->getBlackUser() == NULL) {
        $this->game
          ->setBlackUser($user)
          ->setStatus(static::STATUS_IN_PROGRESS)
          ->save();
      }
      else {
        \Drupal::logger('VChess')->error(t( "Attempt to set a player when both players are already assigned"));
      }
    }
  }

  /**
   * Get the white player
   */
  public function whitePlayer() {
    return GamerStatistics::loadForUser($this->game->getWhiteUser());
  }
  
  /**
   * Get the the black player
   */
  public function blackPlayer() {
    return GamerStatistics::loadForUser($this->game->getBlackUser());
  }

  /**
   * Get the game board.
   */
  public function getBoard() {
    return $this->board;
  }
  
  /**
   * Set the board
   */
  public function setBoard(Board $board) {
    $this->board = $board;
  }
  
  /**
   * Setup a postion
   * 
   * @param $fen_string
   *   A FEN string, e.g. after 1.e4 the FEN string will be:
   *   "rnbqkbnr/pppppppp/8/8/4P3/8/PPPP1PPP/RNBQKBNR"
   */
  public function setupPosition($fen_string) {
    $this->board->setupPosition($fen_string);
  }

//  /**
//   * Get the current position as a FEN string
//   *
//   * @return string
//   *   the current position as a FEN string e.g. after 1.e4 the FEN string will be:
//   *   "rnbqkbnr/pppppppp/8/8/4P3/8/PPPP1PPP/RNBQKBNR"
//   */
//  public function position() {
//    return $this->board->getFenString();
//  }
//
//  /**
//   * Get the uid of the white player
//   */
//  public function whiteUid() {
//    return $this->game->getWhiteUser()->id();
//  }
//
//  /**
//   * Get the uid of the black player
//   */
//  public function blackUid() {
//    return $this->game->getBlackUser()->id();
//  }
  
//  /**
//   * Get the en_passant
//   *
//   * The en_passant is the coordinates of the square
//   * behind the pawn in the last move to have moved 2 squares.
//   *
//   * @return
//   *   Returns the en_passant coord (e.g. "d3"), if there is one,
//   *   otherwise it returns "-"
//   */
//  public function getEnPassantSquare() {
//    return $this->board->getEnPassantSquare();
//  }
  
//  /**
//   * Get the player whose turn it is, either 'w' or 'b'
//   *
//   * @return
//   *   Whose turn it is, 'w' or 'b'
//   */
//  public function getTurn() {
//    return $this->game->getTurn();
//  }
  
//  /**
//   * Get the status
//   *
//   * Status can be one of:
//   * - "awaiting players"
//   * - "in progress"
//   * - "1-0"
//   * - "0-1"
//   * - "1/2-1/2"
//   */
//  public function getStatus() {
//    return $this->game->getStatus();
//  }
  
//  /**
//   * Set the player whose turn it is to move to be 'w'
//   */
//  public function setTurnWhite() {
//    $this->game->setTurn('w');
//  }
  
//  /**
//   * Set the player whose turn it is to move to be 'b'
//   */
//  public function setTurnBlack() {
//    $this->game->setTurn('b');
//  }
  
//  /**
//   * Checks whether the king is in checkmate.
//   *
//   * @param string $player
//   *   Player, either 'w' or 'b'
//   *
//   * @return boolean
//   */
//  public function isCheckmate($player) {
//    return $this->board->isInCheckmate($player);
//  }
  
//  /**
//   * Checks whether the king is in check.
//   *
//   * @param string $player
//   *   Player, either 'w' or 'b'
//   *
//   * @return boolean
//   */
//  public function isCheck($player) {
//    return $this->board->isInCheck($player);
//  }
  
  /**
   * Resigns a particular game.
   */
  public function resign(UserInterface $user) {
    if ($this->playerColor($uid) === 'w') {
      $this->game
        ->setStatus(static::STATUS_BLACK_WIN)
        ->save();
    }
    else {
      $this->game
        ->setStatus(static::STATUS_WHITE_WIN)
        ->save();
    }
  }
  
  /**
   * Find out what color a particular player is
   * 
   * In the case where a player is playing against themself (!), which we allow
   * at least for testing purposes, the color is the color of whoever's turn it 
   * is to move.
   * 
   * @param $uid
   *   The user id of a player
   *   
   * @return
   *   'w' if the player is playing white
   *   'b' if the player is playing black
   *   '' if the player is not a player of this game
   */
  public function playerColor(UserInterface $user) {
    // @todo
    if ($this->game->getWhiteUser()->id() === $this->game->getBlackUser()->id()) {
      return $this->game->getTurn();
    }
    elseif ($this->game->getWhiteUser()->id() == $user->id()) {
      return 'w';
    }
    elseif ($this->game->getBlackUser()->id() == $user->id()) {
      return 'b';
    }
  
    return "";
  }
  
  /**
   * Set status
   */
  public function setStatus($status) {
    $this->game->setStatus($status);
  }
  
  public function setCastling($color, $side, $allowed) {
    $this->castling[$color][$side] = (bool) $allowed;
    return $this;
  }
  
  public function getCastling($color, $side) {
    return $this->castling[$color][$side];
  }

  public function setCastlingForColor($color, $allowed) {
    $this->castling[$color]['Q'] = (bool) $allowed;
    $this->castling[$color]['K'] = (bool) $allowed;
    return $this;
  }

  public function getCastlingString() {
    $castling = '';
    if ($this->castling['w']['K']) {
      $castling .= 'K';
    }
    if ($this->castling['w']['Q']) {
      $castling .= 'Q';
    }
    if ($this->castling['b']['K']) {
      $castling .= 'k';
    }
    if ($this->castling['b']['Q']) {
      $castling .= 'q';
    }
    return $castling;
  }

  public function offerDraw() {
// HANDLE MOVES:
    if ($move_string === 'draw?' && $this->game->getStatus() === '?') {
      // Offer draw
      $this->game->setStatus('D');
      $result .= 'You have offered a draw.';
      $draw_handled = 1;
      $this->lastMove = 'DrawOffered';
    }
  }

  public function acceptDraw(UserInterface $user) {
    if ($this->game->getStatus() === 'D') {
        // Accept draw
      $this->game->setStatus('-');
      $draw_handled = 1;
      $result = 'You accepted the draw.';
      $this->lastMove = 'DrawAccepted';
      if ($this->game->getTurn() === 'b') {
        $this->game->setCurrentMove($this->game->getCurrentMove() + 1); // new move as white offered
      }
      $game['mhistory'][count($game['mhistory'])] = 'draw';
    }
  }


  public function rejectDraw(UserInterface $user) {
    if ($this->game->getStatus() === 'D') {
        // Refuse draw
      $this->game->setStatus('?');
      $draw_handled = 1;
      $result = 'You refused the draw.';
      $this->lastMove = 'DrawRefused';
    }
  }

  protected function isCastlingMove($move) {
    return $move === 'Ke1-g1' || $move === 'Ke8-g8' || $move === 'Ke1-c1' || $move === 'Ke8-c8';
  }

  protected function castleMove(Move $move) {
    $error = '';
    switch ($move->getLongMove()) {
      case 'Ke1-g1':
        $error = $this->castle('w', 'e1', 'g1', 'h1', 'f1', ['f1', 'g1']);
        break;
      case 'Ke1-c1':
        $error = $this->castle('w', 'e1', 'c1', 'a1', 'd1', ['b1', 'c1', 'd1']);
        break;
      case 'Ke8-g8':
        $error = $this->castle('b', 'e8', 'g8', 'h8', 'f8', ['f8', 'g8']);
        break;
      case 'Ke8-c8':
        $error = $this->castle('b', 'e8', 'c8', 'a8', 'd8', ['b8', 'c8', 'd8']);
        break;
    }
    return $error;
  }
  
  /**
   * Verify move, execute it and modify game.
   *
   * @param \Drupal\user\UserInterface $user
   *   User id of current player
   *   
   * @param \Drupal\vchess\Entity\Move $move
   *   A move object representing the current move.
   *   
   * @return boolean
   *   true for successful move, false for unsuccessful.
   */
  public function makeMove(UserInterface $user, Move $move, array &$messages, array &$errors) {
    $move->setGameId($this->game->id())
      ->setColor($this->game->getTurn());

    if (!$this->game->isPlayersMove($user)) {
      $errors[] = 'It is not your turn!';
      return FALSE;
    }
    else {
      $move_ok = TRUE;
      $messages = [];
      $en_passant_set = FALSE;
      $clone_board = (new Board())
        ->setupPosition($this->board->getFenString())
        ->setEnPassantSquare($this->board->getEnPassantSquare());

      if ($this->isCastlingMove($move->getLongMove())) {
        $error = $this->castleMove($move);
        if ($error !== '') {
          $errors[] = $error;
          return FALSE;
        }
      }
      elseif ($move->getType() === '-') {
        // Validate piece and position.
        // Move is e.g. "Nb1-c3"
        $piece = (new Piece())
          ->setType($move->getSourcePieceType())
          ->setColor($this->game->getTurn());

        if ($piece->getType() === 'P' && $move->toSquare()->getCoordinate() === $this->board->getEnPassantSquare()) {
          // Perform en passant pawn capture.
          $this->board->performEnPassantCapture($move->fromSquare(), $move->toSquare());
        }
        elseif (!$this->board->moveIsOk($move->fromSquare(), $move->toSquare())) {
          $move_ok = FALSE;
        }
        else {
          // If pawn moved 2 squares, then record the en_passant square
          // (the square behind the pawn which has just moved)
          if ($this->board->pawnMoved2Squares($move->fromSquare(), $move->toSquare())) {
            $file = $move->toSquare()->getFile();
            if ($move->toSquare()->getRank() == 4) {
              // White has moved something like "Ph2-h4"
              // so target will be "h3"
              $this->board->setEnPassantSquare($file . "3");
            }
            else {
              // Black has moved something like "Ph7-h5"
              // so target will be "h6"
              $this->board->setEnPassantSquare($file . "6");
            }

            $en_passant_set = TRUE;
          } 
          
          // If pawn reached last rank, promote it
          $pawn_promoted = $this->handlePawnPromotion($move);
        
          if (!$pawn_promoted) {
            // Perform normal move
            $this->board->movePiece($move->fromSquare(), $move->toSquare());
          }
        }
      }
      elseif ($move->getType() === 'x') {
        if ($this->board->squareIsEmpty($move->toSquare())) {
          // En passant of pawn?
          if ($move->getSourcePieceType() === 'P') {
            $move_ok = TRUE;
          }
          else {
            $errors[] = new TranslatableMarkup('ERROR: @square is empty!',
              ['@square' => $move->toSquare()->getCoordinate()]);
            $move_ok = FALSE;
          }
        }
        elseif ($this->board->getPiece($move->toSquare())->getColor() === $this->game->getTurn()) {
          $errors[] = new TranslatableMarkup('ERROR: You cannot attack own chessman at @square!',
            ['@square' => $move->toSquare()->getCoordinate()]);
          $move_ok = FALSE;
        }
        elseif (!$this->board->pieceAttacks($move->fromSquare(), $move->toSquare())) {
          $errors[] = new TranslatableMarkup('ERROR: You cannot take the piece on @square!',
            ['@square' => $move->toSquare()->getCoordinate()]);
          $move_ok = FALSE;
        }
        
        // If pawn reached last rank, promote it
        $pawn_promoted = $this->handlePawnPromotion($move);
        
        if ($move_ok && !$pawn_promoted) {
          // Perform normal capture
          $this->board->movePiece($move->fromSquare(), $move->toSquare());
        }
      }
    
      // If OWN king is still in check, then invalid move
      if ($this->board->isInCheck($move->getColor())) {
        $errors[] = new TranslatableMarkup('ERROR: King is in check.');
        $move_ok = FALSE;        
      }

      $move->calculateAlgebraic($this->game->getTurn(), $clone_board);
      
      // If move was executed update game state.
      if ($move_ok) {
        $messages[] = new TranslatableMarkup('Your last move: @move', ['@move' => $move->getAlgebraic()]);

        // If it is a Rook or King move, invalidate castling as necessary.
        $piece_type = $this->board->getPiece($move->toSquare())->getType();
        if ($piece_type === 'K') {
          $this->setCastlingForColor($this->game->getTurn(), FALSE);
        }
        else if ($piece_type === 'R') {
          if ($move->fromSquare()->getFile() === 'a') {
            $this->setCastling($this->game->getTurn(), 'Q', FALSE);
          }
          else if ($move->fromSquare()->getFile() === 'h') {
            $this->setCastling($this->game->getTurn(), 'K', FALSE);
          }
        }

        // If this wasn't a 2-square pawn move, we need to reset
        // the en_passant square.
        if (!$en_passant_set) {
          $this->board->resetEnPassantSquare();
        }
        
        // Check checkmate/stalemate.
        $opponent = $this->game->getPlayerColor($user) === 'w' ? 'b' : 'w';
        if ($this->board->isInCheck($opponent)) {
          // If this is checkmate finish the game, otherwise add '+' to the move.
          if ($this->board->isInCheckmate($opponent)) {
            if ($this->game->getTurn() === 'w') {
              $this->setStatus(static::STATUS_WHITE_WIN);
            }
            else {
              $this->setStatus(static::STATUS_BLACK_WIN);
            }
            $messages[] = '... CHECKMATE!';
          }
        }
        elseif ($this->board->isStalemate($opponent)) {
          $this->setStatus(static::STATUS_DRAW);
          $messages[] = '... STALEMATE!';
        }
        
        // Update game board position and castling status.
        $this->game->setBoard($this->board->getFenString());
        $this->game->setCastling($this->getCastlingString());

        // Append move to scoresheet.
        $this->game->getScoresheet()->appendMove($move);

        // Update whose turn it is.  Even if mate has occurred, it
        // is still logically the opponents move, even if they have
        // no valid move that they can make
        if ($this->game->getTurn() === 'b') {
          $this->game->setTurn('w');
        }
        else {
          $this->game->setTurn('b');
        }

        // Add comment to head of chatter. Right now we have only two
        // chatter items. Strip backslashes and replace newlines to get
        // a single line.
        if (empty($comment)) {
          $comment = '(silence)';
        }
        else {
          $comment = str_replace("\\", '', strip_tags($comment));
          $comment = str_replace("\n", '<br />', $comment);
        }
        $comment = '<u>' . $user->id() . '</u>: ' . $comment;
        //    $game['chatter'][1] = $game['chatter'][0];
        //    $game['chatter'][1] = "Hugh hard coding for now";
        //    $game['chatter'][0] = $comment;
      }
      else {
        $errors[] = 'ERROR: ' . $move->getAlgebraic() . ' is not a legal move!  ';
      }
    }
    return empty($errors);
  }

  /**
   * Handle castling
   *
   * @param string $turn
   *   Whose turn to play, either 'w' or 'b'.
   * @param string $king_from
   *   The coordinate of the king, e.g. "e1"
   * @param array $castling_coords
   *   Array of the coords of all the squares involved, from
   * left to right, e.g. array("e1", "f1", "g1", "h1"
   *
   * @return string
   *
   */
  public function castle($turn, $king_from, $king_to, $rook_from, $rook_to, $gap_coords) {
    $error = "";

    if ($turn == 'w') {
      $opponent = 'b';
    }
    else {
      $opponent = 'w';
    }

    if (count($gap_coords) === 2) {
      $side = 'K';
      if (!$this->getCastling($turn, $side)) {
        $error = static::ERROR_CANNOT_CASTLE_SHORT;
      }
    }
    else { // count == 3
      $side = 'Q';
      if (!$this->getCastling($turn, $side)) {
        $error = static::ERROR_CANNOT_CASTLE_LONG;
      }
    }

    if ($error === "") {
      foreach ($gap_coords as $gap_coord) {
        if (!$this->board->squareAtCoordinateIsEmpty($gap_coord)) {
          $error = static::ERROR_CASTLING_SQUARES_BLOCKED;
        }
      }
    }

    if ($error == "") {
      if ($this->board->isInCheck($turn)) {
        $error = static::ERROR_CANNOT_ESCAPE_CHECK_BY_CASTLING;
      }
    }
    // Ensure the squares between the king's current position and where he will
    // move to are not under attack.
    if ($error === "") {
      foreach ($gap_coords as $gap_coord) {
        $square = (new Square)->setCoordinate($gap_coord);
        if ($this->board->squareIsUnderAttack($square, $opponent)) {
          $error = static::ERROR_CANNOT_CASTLE_ACROSS_CHECK;
        }
      }
    }

    if ($error === "") {
      $this->board->movePiece(Square::fromCoordinate($king_from), Square::fromCoordinate($king_to));  // White King
      $this->board->movePiece(Square::fromCoordinate($rook_from), Square::fromCoordinate($rook_to));  // Rook

      // Castling can only happen once. So all options are off.
      $this->setCastlingForColor($turn, FALSE);
    }

    return $error;
  }

  /**
   * Handle pawn promotion
   */
  public function handlePawnPromotion(Move $move) {
    $pawn_promoted = FALSE;
    
    $piece = $this->board->getPiece($move->fromSquare());
    if ($piece->getType() === 'P') {
      if (($this->game->getTurn() === 'w' && $move->toSquare()->getRank() == 8) ||
          ($this->game->getTurn() === 'b' && $move->toSquare()->getRank() == 1)) {
        $promote_to = (new Piece())
          ->setType($move->getPromotionPieceType())
          ->setColor($this->game->getTurn());
    
        $this->board->promotePawn($move->fromSquare(), $move->toSquare(), $promote_to);
        
        $pawn_promoted = TRUE;
      }
    }
    
    return $pawn_promoted;
  }
  
  /**
   * Abort an open game. This is only possible if your opponent did not move
   * at all yet or did not move for more than four weeks. Aborting a game will
   * have NO influence on the game statistics. Return a status message.
   *
   * @todo
   */
  function abort(UserInterface $user) {
//    if ($game == NULL) {
//      return 'ERROR: Game "' . $gid . '" does not exist!';
//    }
    if (!$this->game->mayAbort()) {
      return 'ERROR: You cannot abort the game!';
    }
    else {
      $this->game->setStatus(static::STATUS_ABORTED);
      $this->game->delete();
    }

    return new TranslatableMarkup('Game "@game" deleted.', ['@game' => $this->game->id()]);
  }
  
  /**
   * Undo last move (only possible if game is not over yet).
   */
  function handle_undo($uid) {

  }

  /**
   * HACK: this function checks whether en-passant is possible
   */
  public static function enPassantIsOk($player, $pos, $dest, $opp_ep_flag) {
    if ($opp_ep_flag != 'x') {
      if ($dest % 8 == $opp_ep_flag) {
        // if (pawn_attacks($pos,$dest)) right now
        // this is not required as we only use this
        // function in isStaleMate which uses correct dests
        if (($player == 'w' && floor($dest / 8) == 5)
          || ($player == 'b' && floor($dest / 8) == 2)) {
          return 1;
        }
      }
    }
    return 0;
  }

  /**
   * Check through all the current games to see if any have been lost on time
   *
   * If games are found which are lost on time, then the game is finished and
   * the player statistics are updated
   */
  public static function checkForLostOnTimeGames() {
    $games = Game::loadAllCurrentGames();

    foreach ($games as $game) {
      if ($game->isLostOnTime()) {
        GamerStatistics::updatePlayerStatistics($game);
      }
    }
  }

}
