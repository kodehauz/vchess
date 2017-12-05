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
   * Game constructor.
   *
   * @param Game|null $game
   *   A game to initialize GamePlay with. Leave blank to start with standard
   *   board.
   */
  public function __construct(Game $game = NULL) {
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
    return FALSE;
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

  /**
   * Resigns a particular game.
   */
  public function resign(UserInterface $user) {
    if ($this->playerColor($user) === 'w') {
      $this->game->setStatus(static::STATUS_BLACK_WIN)->save();
    }
    else {
      $this->game->setStatus(static::STATUS_WHITE_WIN)->save();
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
    if ($this->game->getWhiteUser() === $this->game->getBlackUser()) {
      return $this->game->getTurn();
    }
    return $this->game->getPlayerColor($user);
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

    $move_ok = TRUE;
    $messages = [];
    $en_passant_set = FALSE;
    $clone_board = (new Board())
      ->setupPosition($this->board->getFenString())
      ->setEnPassantSquare($this->board->getEnPassantSquare());

    if ($this->board->isValidCastlingMove($move->squareFrom(), $move->squareTo())) {
      if ($error = $this->castle($this->playerColor($user), $move->squareFrom(), $move->squareTo())) {
        $errors[] = $error;
        $move_ok = FALSE;
      }
    }
    elseif ($move->getType() === '-') {
      // Validate piece and position.
      // Move is e.g. "Nb1-c3"
      $piece = (new Piece())
        ->setType($move->getSourcePieceType())
        ->setColor($this->game->getTurn());

      if ($piece->getType() === 'P' && $move->squareTo()->getCoordinate() === $this->board->getEnPassantSquare()) {
        // Perform en passant pawn capture.
        $this->board->performEnPassantCapture($move->squareFrom(), $move->squareTo());
      }
      elseif (!$this->board->moveIsOk($move->squareFrom(), $move->squareTo())) {
        $move_ok = FALSE;
      }
      else {
        // If pawn moved 2 squares, then record the en_passant square
        // (the square behind the pawn which has just moved)
        if ($this->board->pawnMoved2Squares($move->squareFrom(), $move->squareTo())) {
          $file = $move->squareTo()->getFile();
          if ($move->squareTo()->getRank() == 4) {
            // White has moved something like "Ph2-h4"
            // so target will be "h3"
            $this->board->setEnPassantSquare($file . '3');
          }
          else {
            // Black has moved something like "Ph7-h5"
            // so target will be "h6"
            $this->board->setEnPassantSquare($file . '6');
          }

          $en_passant_set = TRUE;
        }

        // If pawn reached last rank, promote it
        $pawn_promoted = $this->handlePawnPromotion($move);

        if (!$pawn_promoted) {
          // Perform normal move
          $this->board->movePiece($move->squareFrom(), $move->squareTo());
        }
      }
    }
    elseif ($move->getType() === 'x') {
      if ($this->board->squareIsEmpty($move->squareTo())) {
        // En passant of pawn?
        if ($move->getSourcePieceType() === 'P') {
          $move_ok = TRUE;
        }
        else {
          $errors[] = new TranslatableMarkup('ERROR: @square is empty!',
            ['@square' => $move->squareTo()->getCoordinate()]);
          $move_ok = FALSE;
        }
      }
      elseif ($this->board->getPiece($move->squareTo())->getColor() === $this->game->getTurn()) {
        $errors[] = new TranslatableMarkup('ERROR: You cannot attack own chessman at @square!',
          ['@square' => $move->squareTo()->getCoordinate()]);
        $move_ok = FALSE;
      }
      elseif (!$this->board->pieceAttacks($move->squareFrom(), $move->squareTo())) {
        $errors[] = new TranslatableMarkup('ERROR: You cannot take the piece on @square!',
          ['@square' => $move->squareTo()->getCoordinate()]);
        $move_ok = FALSE;
      }

      // If pawn reached last rank, promote it
      $pawn_promoted = $this->handlePawnPromotion($move);

      if ($move_ok && !$pawn_promoted) {
        // Perform normal capture
        $this->board->movePiece($move->squareFrom(), $move->squareTo());
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
      $piece_type = $this->board->getPiece($move->squareTo())->getType();
      if ($piece_type === 'K') {
        $this->setCastlingForColor($this->game->getTurn(), FALSE);
      }
      else if ($piece_type === 'R') {
        if ($move->squareFrom()->getFile() === 'a') {
          $this->setCastling($this->game->getTurn(), 'Q', FALSE);
        }
        else if ($move->squareFrom()->getFile() === 'h') {
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
      $this->addComment();
    }
    else {
      $errors[] = 'ERROR: ' . $move->getAlgebraic() . ' is not a legal move!  ';
    }

    return empty($errors);
  }

  /**
   * Handle castling
   *
   * @param string $turn
   *   Whose turn to play, either 'w' or 'b'.
   * @param \Drupal\vchess\Game\Square $king_from
   *   The coordinate of the king, e.g. "e1".
   * @param \Drupal\vchess\Game\Square $king_to
   *   The coordinate the king is going, e.g. "g1".
   *
   * @return string
   *   An error message if castling cannot be done.
   *
   */
  public function castle($turn, Square $king_from, Square $king_to) {

    if ($king_to->getColumn() - $king_from->getColumn() === 2) {
      $side = 'K';
      if (!$this->getCastling($turn, $side)) {
        return static::ERROR_CANNOT_CASTLE_SHORT;
      }
    }
    else { // count == 3
      $side = 'Q';
      if (!$this->getCastling($turn, $side)) {
        return static::ERROR_CANNOT_CASTLE_LONG;
      }
    }

    if ($this->board->isInCheck($turn)) {
      return static::ERROR_CANNOT_ESCAPE_CHECK_BY_CASTLING;
    }

    $error = $this->board->performCastling($king_from, $king_to);
    if (empty($error)) {
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
    
    $piece = $this->board->getPiece($move->squareFrom());
    if ($piece->getType() === 'P') {
      if (($this->game->getTurn() === 'w' && $move->squareTo()->getRank() == 8) ||
          ($this->game->getTurn() === 'b' && $move->squareTo()->getRank() == 1)) {
        $promote_to = (new Piece())
          ->setType($move->getPromotionPieceType())
          ->setColor($this->game->getTurn());
    
        $this->board->promotePawn($move->squareFrom(), $move->squareTo(), $promote_to);
        
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
  public function handleUndo($uid) {

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

  protected function addComment() {
    /** @todo
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
    return $comment;
     */
  }

}
