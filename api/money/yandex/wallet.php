<?php

class wallet extends api
{
  protected function income_notification()
  {
    global $_POST;

    if (!count($_POST))
    {
      header('400 Empty request');
      echo '400 Empty request';
      exit();
    }

    $this->require_valid_data($_POST);
    $this->new_income($_POST);
  }

  private function require_valid_data($data)
  {
    if ($this->authorise($data))
      return;

    header('401 Data signature invalid');
    echo '401 Data signature invalid';
    exit();
  }

  private function authorise($data)
  {
    $params = conf()->money->yandex->wallet->sign_arguments;

    $signed_data = [];
    foreach ($params as $param)
      if ($param == 'notification_secret')
        $signed_data[] = conf()->money->yandex->wallet->shared_key;
      else
        $signed_data[] = $data[$param];

    $signed_string = implode("&", $signed_data);

    $sign = sha1($signed_string, false);
    return $data['sha1_hash'] == $sign;
  }

  private function new_income($data = [])
  {
    $trans = db::Begin();

    $handle = db::Query("INSERT INTO transactions(system, data) VALUES ($1, $2) RETURNING id",
      ["yandex.wallet", json_encode($data)], true);

    $result = $trans->Commit();

    if (!$result)
    {
      header('500 Failed to store transaction');
      exit();
    }
  }
}