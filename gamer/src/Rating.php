<?php

namespace Drupal\gamer;

class Rating {

  const INITIAL_RATING = 1200;
  const MIN_RATED_GAMES = 5;
  const RATING_CHANGE_MULTIPLIER = 25;

  /**
   * @file
   * Functions to apply game results to rating according to ELO's formula
   */

  /**
   * Compute initial rating based on average opponent strength
   */
  // function vchess_get_initial_rating($uid) {
  //   $player = new Player($uid);

  //   if ($player->played() == 0) {
  //     $rating = INITIAL_RATING;
  //   }

  // //  $rating = $avg_opp_rating + 700 * (($wins + 0.5 * $draws) / $played -0.5) * $played / ($played + 2);

  //   return $rating;
  // }

  /**
   * Get probability for player to win from difference in rating
   *
   * @param $diff
   *   Difference between two players ratings
   *
   * @return
   *   Probability, between 0 and 1, that the player would win
   */
  public static function calculateWinProbability($diff) {
    $absdiff = abs($diff);
    if ($diff > 735) {
      $probability = 1;
    }
    elseif ($diff < -735) {
      $probability = 0;
    }
    else {
      $probability = 0.5
        + 1.4217 * 0.001 * $diff
        - 2.4336 * 0.0000001 * $diff * $absdiff
        - 2.5140 * 0.000000001 * $diff * $absdiff * $absdiff
        + 1.9910 * 0.000000000001 * $diff * $absdiff * $absdiff * $absdiff;
    }

    return $probability;
  }

  /**
   * Get rating change multiplier (coefficient K) which is used to scale expected propability before
   * updating the rating.
   */
  public static function calcRatingChangeMultiplier() {
    //   if ($rating < 2000) {
    //     $K = 30;
    //   }
    //   elseif ($rating > 2400) {
    //     $K = 10;
    //   }
    //   else {
    //     $K = 130 -$rating / 20;
    //   }
    //   return $K;

    return static::RATING_CHANGE_MULTIPLIER;
  }

}