<?php

class jira extends api
{
  protected function income_notification($spawn)
  {
    $rest_json = file_get_contents("php://input");
    $stash_data = json_decode($rest_json, true);

    db::Query("UPDATE requests SET post=$2 WHERE id=$1",
      [
        REQUEST_ID,
        $rest_json,
      ]);

    $this->new_event($spawn, $stash_data);
  }

  private function new_event($spawn, $data)
  {
    $res = db::Query("INSERT INTO jira(spawn, data) VALUES ($1, $2) RETURNING id",
      [
        $spawn,
        json_encode($data, true),
      ], true);
  }
}
