<?php

namespace Drupal\Tests\vchess\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\vchess\Entity\Move;

/**
 * @group vchess
 * @coversDefaultClass \Drupal\vchess\Entity\Move
 */
class MoveTest extends KernelTestBase {

  public static $modules = ['system', 'user', 'vchess', 'pos', 'gamer'];

  public function setUp() {
    parent::setUp();

    $this->installSchema('system', 'sequences');
    $this->installEntitySchema('user');
    $this->installEntitySchema('vchess_move');
  }

  public function testGetterSetters() {
    $game_id = rand(1, 10);
    $move_no = rand(1, 10);
    $longmove = $this->randomString();
    $algebraic = $this->randomString();

    /** @var \Drupal\vchess\Entity\Move $move */
    $move = Move::create()
      ->setGameId($game_id)
      ->setMoveNo($move_no)
      ->setColor('b')
      ->setLongMove($longmove)
      ->setAlgebraic($algebraic)
      ->setTimestamp(1234555);
    $move->save();

    /** @var \Drupal\vchess\Entity\Move $game_move */
    $game_move = Move::load($move->id());
    $this->assertEquals($game_id, $game_move->getGameId());
    $this->assertEquals($move_no, $game_move->getMoveNo());
    $this->assertEquals('b', $game_move->getColor());
    $this->assertEquals($longmove, $game_move->getLongMove());
    $this->assertEquals($algebraic, $game_move->getAlgebraic());
    $this->assertEquals(1234555, $game_move->getTimestamp());
  }

}
