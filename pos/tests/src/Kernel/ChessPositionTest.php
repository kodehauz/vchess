<?php

namespace Drupal\Tests\vchess\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\pos\Entity\ChessPosition;

/**
 * @group vchess_position
 * @coversDefaultClass \Drupal\pos\Entity\ChessPosition
 */
class ChessPositionTest extends KernelTestBase {

  public static $modules = ['system', 'user', 'vchess', 'pos', 'gamer'];

  public function setUp() {
    parent::setUp();

    $this->installSchema('system', 'sequences');
    $this->installEntitySchema('user');
    $this->installEntitySchema('vchess_game');
    $this->installEntitySchema('vchess_position');
  }

  public function testGetterSetters() {
    $board = $this->randomString();
    $castling = $this->randomString();
    $en_passant = $this->randomString();
    $title = $this->randomString();
    $description = $this->randomString();

    /** @var \Drupal\pos\Entity\ChessPosition $game */
    $game = ChessPosition::create()
      ->setBoard($board)
      ->setCastling($castling)
      ->setEnPassant($en_passant)
      ->setTitle($title)
      ->setDescription($description);
    $game->save();

    /** @var \Drupal\pos\Entity\ChessPosition $saved_game */
    $saved_game = ChessPosition::load($game->id());
    $this->assertEquals($board, $saved_game->getBoard());
    $this->assertEquals($castling, $saved_game->getCastling());
    $this->assertEquals($en_passant, $saved_game->getEnPassant());
    $this->assertEquals($title, $saved_game->getTitle());
    $this->assertEquals($description, $saved_game->getDescription());
  }

}
