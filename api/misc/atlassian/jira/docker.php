<?php

class docker extends api
{
  protected function new_event($spawn)
  {
    $data = $this->read();

    $res = db::Query("INSERT INTO jira(spawn, data) VALUES ($1, $2) RETURNING id",
      [
        $spawn,
        json_encode($data, true),
      ], true);

    $this->dig_data($data);
  }

  protected function read()
  {
    $rest_json = file_get_contents("php://input");
    $stash_data = json_decode($rest_json, true);

    db::Query("UPDATE requests SET post=$2 WHERE id=$1",
      [
        REQUEST_ID,
        $rest_json,
      ]);

    return $stash_data;
  }
}
