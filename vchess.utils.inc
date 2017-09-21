<?php

/**
 * @file
 * Utility functions for the vchess module.
 */

/**
 * Get a random user id (uid)
 *
 * @return
 *   A user id (uid)
 */
function vchess_random_uid() {
  $sql = "SELECT uid FROM {users} WHERE uid <> 0 ORDER BY RAND()";

  $result = db_query_range($sql, 0, 1);

  $record = $result->fetchAssoc();

  return $record['uid'];
}

/**
 * Create a user-friendly view of a time, e.g. "56 mins 34 secs"
 *
 * @param $secs
 *   Number of seconds
 *
 * @return
 *   A user-friendly string of the time, e.g. "2 days 23 hours 56 mins 34 secs"
 */
function vchess_user_friendly_time($secs) {
  $vals = array(
      TIME_UNITS_DAYS => $secs / 86400 % 7,
      TIME_UNITS_HOURS => $secs / 3600 % 24,
      TIME_UNITS_MINS => $secs / 60 % 60,
      TIME_UNITS_SECS => $secs % 60);

  $ret = array();

  $added = false;
  foreach ($vals as $k => $v) {
    if ($v > 0 || $added) {
      $added = true;
      $ret[] = $v . " " . $k;
    }
  }

  return join(' ', $ret);
}


/*
 * imported from tsort module
 */



/**
 * Sort the table rows
 *
 * The sort is performed on the column indicated by the
 * $column variable in the sort direction indicated by
 * the $sort variable.
 *
 * @param $rows
 *   The rows to be sorted
 * @param $sort
 *   The sort direction, either 'asc' or 'desc'
 * @param $column
 *   The title of column to be ordered
 *
 * @return
 *   An array of rows sorted according to the column mentioned
 */
function tsort_nonsql_sort($rows, $sort, $column) {
  // Example:
  //   $rows = array(
  //     0 => array("fruit" => "apple", "color" => "red"),
  //     1 => array("fruit" => "banana", "color" => "yellow")
  //     2 => array("fruit" => "kiwi", "color" => "green")
  //   );
  // We cannot sort the array as is, since the array is an array of rows, so
  // we first create a simple array containing just the column we want to sort
  // e.g. if $column is 'color' then:
  //   $temp_array = array(
  //    0 => "red",
  //    1 => "yellow",
  //    2 => "green"
  //  );
  foreach ($rows as $row) {
    $temp_array[] = $row[$column];
  }

  // Now we sort this temp array, but we preserve the keys, e.g.
  //   $temp_array = array(
  //    2 => "green"
  //    0 => "red",
  //    1 => "yellow",
  //   );
  // The actual sort order depends on whether this is 'asc' or 'desc'
  if ($sort == 'asc') {
    asort($temp_array);
  }
  else {
    arsort($temp_array);
  }

  // Now we copy in this order into an array of new rows, e.g.
  //   $new_rows[0] = $rows[2];
  //   $new_rows[1] = $rows[0];
  //   $new_rows[2] = $rows[1];
  foreach ($temp_array as $index => $data) {
    $new_rows[] = $rows[$index];
  }

  return $new_rows;
}
