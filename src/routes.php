<?php

use Slim\Http\Request;
use Slim\Http\Response;
use Respect\Validation\Validator as V;

// Routes

$app->get('/', function (Request $request, Response $response, array $args) {
  // Sample log message
  //$this->logger->info("Slim-Skeleton '/' route");

  // Render index view
  if (isset($this->session->uid)) {
    return $response->withRedirect("/dashboard");
  } else {
    return $this->renderer->render($response, 'index.phtml', $args);
  }
});

$app->post('/login', function (Request $request, Response $response) {
  $input = $request->getParsedBody();

  $sql = "SELECT * FROM users WHERE username = :inputLogin OR email = :inputLogin";
  $sth = $this->db->prepare($sql);
  $sth->bindParam("inputLogin", filter_var($input['inputLogin'], FILTER_SANITIZE_STRING));
  $sth->bindParam("inputPassword", $input['inputPassword']);
  $sth->execute();
  $user = $sth->fetchAll();

  if (password_verify($input['inputPassword'], $user[0]['password'])) {
    $session = $this->session;
    $session->set('uid', $user[0]['id']);
    $session->set('role', $user[0]['role_id']);
    $session->set('username', $user[0]['username']);
    return $response->withRedirect("/dashboard");
  } else {
    // TODO: Wrong password message
    return $response->withRedirect("/");
  }
});

$app->post('/register', function (Request $request, Response $response) {

  $input = $request->getParsedBody();
  $sql = "INSERT INTO users (role_id, username, email, password, f_name, l_name, b_date, created_at, phone) VALUES (1, :username, :email, :password, :firstname, :lastname, :dateofbirth, :created_at, :phone)";
  $sth = $this->db->prepare($sql);

  $sth->bindParam("username", filter_var($input['username'], FILTER_SANITIZE_STRING));
  $sth->bindParam("email", filter_var($input['email'], FILTER_SANITIZE_EMAIL));
  $sth->bindParam("password", password_hash($input['password'], PASSWORD_DEFAULT));
  $sth->bindParam("firstname", $input['firstname']);
  $sth->bindParam("lastname", $input['lastname']);
  $sth->bindParam("dateofbirth", $input['dateofbirth']);
  $sth->bindParam("created_at", date('Y-m-d H:i:s'));
  $sth->bindParam("phone", json_encode($phones = array()));

  $this->validator->request($request, [
    'username' => [
      'rules' => V::length(3, 25)->alnum('_')->noWhitespace(),
      'messages' => [
        'noWhitespace' => 'Username shouldn\'t contain any white spaces.',
        'alnum' => 'Username must contain only letters (a-z), digits (0-9) and "_".',
        'length' => 'Username should be 3 to 25 characters long.'
      ]
    ],
    'password' => [
      'rules' => V::noWhitespace()->length(6, 25),
      'messages' => [
        'length' => 'The password length must be between {{minValue}} and {{maxValue}} characters.',
        'noWhitespace' => 'The password shouldn\'t contain any white spaces.'
      ]
    ],
    'email' => [
      'rules' => V::email(),
      'messages' => [
        'email' => 'The email entered is not of a correct email format.'
      ]
    ],
    'firstname' => [
      'rules' => V::length(1, 25)->alpha()->noWhitespace(),
      'messages' => [
        'noWhitespace' => 'First name shouldn\'t contain any white spaces.',
        'alpha' => 'First name needs to contains alpha characters only.',
        'length' => 'First name should be 1 to 25 characters long.'
      ]
    ],
    'lastname'=> [
      'rules' => V::length(1, 25)->alpha(),
      'messages' => [
        'alpha' => 'Last name needs to contains alpha characters only.',
        'length' => 'Last name should be 1 to 25 characters long.'
      ]
    ],
  ]);

  // if email or username already registered
  $sql2 = "SELECT * FROM users WHERE username=:username OR email=:email";
  $sth2 = $this->db->prepare($sql2);

  $sth2->bindParam("username", filter_var($input['username'], FILTER_SANITIZE_STRING));
  $sth2->bindParam("email", filter_var($input['email'], FILTER_SANITIZE_EMAIL));

  $sth2->execute();

  if($sth2->fetchAll()) {
    $this->validator->addError('username', 'This username/email is already used.');
  }

  if ($this->validator->isValid()) {
    $sth->execute();
    $lastInsertId=$this->db->lastInsertId();
    //$this->flash('success', 'Your account has been created.');
    if ($lastInsertId) {
      $session = $this->session;
      $session->set('uid', $lastInsertId);
      $session->set('role', 1);
      $session->set('username', filter_var($input['username'], FILTER_SANITIZE_STRING));
    }

    return $response->withRedirect("/dashboard");

  } else {
    $errors = $this->validator->getErrors();
    return $this->renderer->render($response, 'index.phtml',  ['errors' => $errors]);
  }

});

$checkIfExist = function ($request, $response, $next) {

  /*
  $session = $this->session;
  if (isset($session->uid)) {
    $response = $next($request, $response);
  } else {
    return $response->withRedirect("/");
  }
  return $response;
  */
};
//Checking if user logged in
$isAuthorized = function ($request, $response, $next) {
  $session = $this->session;
  if (isset($session->uid)) {
    $response = $next($request, $response);
  } else {
    return $response->withRedirect("/");
  }
  return $response;
};

//Checking if user has access to Assignment 3 and Assignment 4
$hasAccess = function ($request, $response, $next) {
  $session = $this->session;
  if ($session->role == 2) {
    $response = $next($request, $response);
  } else {
    return $response->withRedirect("/dashboard");
  }
  return $response;
};

$app->group('/api', function () use ($app) {

  $app->get('/get-state-list', function (Request $request, Response $response, array $args) {

    $sql = "SELECT id, name FROM states WHERE country_id = :country_id";
    $sth = $this->db->prepare($sql);

    $sth->bindParam("country_id", $_GET['country_id']);
    $sth->execute();
    $states = $sth->fetchAll();
    if($states) {
      return $response->withJson($states);
    } else {
      return $response->withJson([]);
    }

  });
  $app->get('/get-city-list', function (Request $request, Response $response, array $args) {
    $sql = "SELECT id, name FROM cities WHERE state_id = :state_id";
    $sth = $this->db->prepare($sql);

    $sth->bindParam("state_id", $_GET['state_id']);
    $sth->execute();
    $cities = $sth->fetchAll();
    if($cities) {
      return $response->withJson($cities);
    } else {
      return $response->withJson([]);
    }
  });
  $app->get('/removePhone', function (Request $request, Response $response) {
    $session = $this->session;

    $sql1 = "SELECT phone FROM users WHERE id = :uid";
    $sth1 = $this->db->prepare($sql1);
    $sth1->bindParam("uid", $session->uid);
    $sth1->execute();

    $sql2 = "UPDATE users
    SET phone = :updatedPhone
    WHERE id = :uid";
    $sth2 = $this->db->prepare($sql2);
    $sth2->bindParam("uid", $session->uid);

    $phones = $sth1->fetchAll();
    $phoneArray = json_decode($phones[0]['phone']);

    if (($key = array_search($_GET['phoneDelete'], $phoneArray)) !== false) {
      unset($phoneArray[$key]);
    }
    $phoneArray = array_values($phoneArray);

    $sth2->bindParam("updatedPhone", json_encode($phoneArray));
    $sth2->execute();
    // TODO: Confirmation message
    return $response->withRedirect("/dashboard");
  });
})->add($isAuthorized);

$app->group('/phonebook', function () use ($app) {

  $app->get('', function (Request $request, Response $response, array $args) {
    return $this->renderer->render($response, 'phonebook.phtml', $args);
  });

  $app->get('/get', function (Request $request, Response $response, array $args) {
    $sql = "SELECT * FROM contacts WHERE user_id = :user_id";
    $sth = $this->db->prepare($sql);

    $sth->bindParam("user_id", $this->session->uid);
    $sth->execute();
    $contacts = $sth->fetchAll();
    if($contacts) {
      return $response->withJson($contacts);
    } else {
      return $response->withJson([]);
    }
  });

  $app->post('/add', function (Request $request, Response $response) {
    $input = $request->getParsedBody();
    $sql = "INSERT INTO contacts (contact_f_name, contact_l_name, contact_phone, created_at, user_id) VALUES (:fname, :lname, :phone, :created_at, :user_id)";
    $sth = $this->db->prepare($sql);

    $sth->bindParam("fname", filter_var($input['fname'], FILTER_SANITIZE_STRING));
    $sth->bindParam("lname", filter_var($input['lname'], FILTER_SANITIZE_STRING));
    $sth->bindParam("phone", filter_var($input['phone'], FILTER_SANITIZE_STRING));
    $sth->bindParam("created_at", date('Y-m-d H:i:s'));
    $sth->bindParam("user_id", $this->session->uid);
    $sth->execute();
    $lastInsertId=$this->db->lastInsertId();

    return $response->withJson(array("sent" => true, "last_id" => $lastInsertId));

  });

  $app->post('/edit', function (Request $request, Response $response) {
    $input = $request->getParsedBody();
    $sql = "UPDATE contacts
    SET contact_f_name=:contact_f_name,
    contact_l_name=:contact_l_name,
    contact_phone=:contact_phone
    WHERE id=:id
    AND user_id=:uid";
    $sth = $this->db->prepare($sql);
    $session = $this->session;
    $sth->bindParam("uid", $session->uid);
    $sth->bindParam("id", $input['id']);
    $sth->bindParam("contact_f_name", $input['contact_f_name']);
    $sth->bindParam("contact_l_name", $input['contact_l_name']);
    $sth->bindParam("contact_phone", $input['contact_phone']);
    $sth->execute();

    return $response->withJson(array("sent" => true));
  });

  $app->post('/delete', function (Request $request, Response $response) {
    $input = $request->getParsedBody();
    $sql = "DELETE FROM contacts WHERE id=:id";
    $sth = $this->db->prepare($sql);

    $sth->bindParam("id", $input['id']);
    $sth->execute();

    return $response->withJson(array("sent" => true));
  });
})->add($isAuthorized);


$app->group('', function () use ($app) {
  $app->get('/dashboard', function (Request $request, Response $response, array $args) {

    $sql = "SELECT * FROM users WHERE id = :uid";
    $sth = $this->db->prepare($sql);
    $session = $this->session;
    $sth->bindParam("uid", $session->uid);
    $sth->execute();
    $user = $sth->fetchAll();

    $sqlCountries = "SELECT id, name FROM countries";
    $sthCountries = $this->db->prepare($sqlCountries);
    $sthCountries->execute();
    $countries = $sthCountries->fetchAll();

    $sqlStates = "SELECT id, name FROM states WHERE country_id = :country_id";
    $sthStates = $this->db->prepare($sqlStates);
    $sthStates->bindParam("country_id", $user[0]['country']);
    $sthStates->execute();
    $states = $sthStates->fetchAll();

    $sqlCities = "SELECT id, name FROM cities WHERE state_id = :state_id";
    $sthCities = $this->db->prepare($sqlCities);
    $sthCities->bindParam("state_id", $user[0]['state']);
    $sthCities->execute();
    $cities = $sthCities->fetchAll();

    return $this->renderer->render($response, 'dashboard.phtml',  ['user' => $user[0], 'countries' => $countries, 'states' => $states, 'cities' => $cities]);
  });

  $app->post('/search', function (Request $request, Response $response) {
  });

  $app->get('/results', function (Request $request, Response $response, array $args) {
    return $this->renderer->render($response, 'results.phtml', $args);
  });

  $app->get('/sign-out', function (Request $request, Response $response) {
    $session = $this->session;
    $session::destroy();
    return $response->withRedirect("/");
  });

  $app->post('/update', function (Request $request, Response $response) {
    $input = $request->getParsedBody();

    $sql = "UPDATE users
    SET email = :inputEmail,
    f_name = :inputFName,
    l_name = :inputLName,
    b_date = :inputBDate,
    gender = :inputGender,
    country = :inputCountry,
    state = :inputState,
    city = :inputCity,
    interests = :inputInterests
    WHERE id = :uid";
    $sth = $this->db->prepare($sql);
    $session = $this->session;
    $sth->bindParam("uid", $session->uid);
    $sth->bindParam("inputEmail", filter_var($input['inputEmail'], FILTER_SANITIZE_EMAIL));
    $sth->bindParam("inputFName", $input['inputFName']);
    $sth->bindParam("inputLName", $input['inputLName']);
    $sth->bindParam("inputBDate", $input['inputBDate']);
    $sth->bindParam("inputGender", $input['inputGender']);
    $sth->bindParam("inputCountry", $input['inputCountry']);
    $sth->bindParam("inputState", $input['inputState']);
    $sth->bindParam("inputCity", $input['inputCity']);
    $sth->bindParam("inputInterests", $input['inputInterests']);
    $sth->execute();
    // TODO: Confirmation message
    return $response->withRedirect("/dashboard");
  });

  $app->post('/updatePhone', function (Request $request, Response $response) {
    $input = $request->getParsedBody();
    $session = $this->session;

    $sql1 = "SELECT phone FROM users WHERE id = :uid";
    $sth1 = $this->db->prepare($sql1);
    $sth1->bindParam("uid", $session->uid);
    $sth1->execute();

    $sql2 = "UPDATE users
    SET phone = :updatedPhone
    WHERE id = :uid";
    $sth2 = $this->db->prepare($sql2);
    $sth2->bindParam("uid", $session->uid);

    $phones = $sth1->fetchAll();
    $phoneArray = json_decode($phones[0]['phone']);
    array_push($phoneArray, $input['inputPhone']);

    $sth2->bindParam("updatedPhone", json_encode($phoneArray));
    $sth2->execute();
    // TODO: Confirmation message
    return $response->withRedirect("/dashboard");
  });

})->add($isAuthorized);

$app->group('/assignment3', function () use ($app) {
  $app->get('', function (Request $request, Response $response, array $args) {

    $messages = $this->flash->getMessages();
    return $this->renderer->render($response, 'assignment3.phtml', ['messages' => $messages]);
  });

  $app->post('/check', function (Request $request, Response $response) {
    // get input
    $input = $request->getParsedBody();

    // prepare array to include alphanumeric and convert to lowercase
    $results = "no results";
    $chars = array
    (
      array('a','a'),
      array('b','b'),
      array('c','c'),
      array('d','d'),
      array('e','e'),
      array('f','f'),
      array('g','g'),
      array('h','h'),
      array('i','i'),
      array('j','j'),
      array('k','k'),
      array('l','l'),
      array('m','m'),
      array('n','n'),
      array('o','o'),
      array('p','p'),
      array('q','q'),
      array('r','r'),
      array('s','s'),
      array('t','t'),
      array('u','u'),
      array('v','v'),
      array('w','w'),
      array('x','x'),
      array('y','y'),
      array('z','z'),
      array('A','a'),
      array('B','b'),
      array('C','c'),
      array('D','d'),
      array('E','e'),
      array('F','f'),
      array('G','g'),
      array('H','h'),
      array('I','i'),
      array('J','j'),
      array('K','k'),
      array('L','l'),
      array('M','m'),
      array('N','n'),
      array('O','o'),
      array('P','p'),
      array('Q','q'),
      array('R','r'),
      array('S','s'),
      array('T','t'),
      array('U','u'),
      array('V','v'),
      array('W','w'),
      array('X','x'),
      array('Y','y'),
      array('Z','z'),
      array('0','0'),
      array('1','1'),
      array('2','2'),
      array('3','3'),
      array('4','4'),
      array('5','5'),
      array('6','6'),
      array('7','7'),
      array('8','8'),
      array('9','9'),
    );

    $palindrome = $input['palindrome'];

    // string length +1
    $i = 0;
    while ($palindrome[$i]) {
      $i++;
    }

    // instead of preg_replace
    $k=0;
    $newCountKey = 0;
    while($k < $i) {
      foreach ($chars as $key => $value) {
        if($palindrome[$k] == $value[0]){
          $convertedPalindromeString = $convertedPalindromeString.$value[1];
          $newCountKey++;
        }
      }
      $k++;
    }

    // fix if sum chars is odd
    if ($newCountKey%2 > 0) {
      $newCountKey++;
    }
    // checking char by char first with last etc. till the middle of string
    $a = 0;
    $b = -1;
    while (($convertedPalindromeString[$a] === $convertedPalindromeString[$b]) && $a<$newCountKey/2) {
      $a++;
      $b--;
    }
    //if half string == to another half reversed => palindrome
    if ($a != $newCountKey/2 || $newCountKey === 0) {
      $results = $palindrome . "<br><b>is not palindrome</b>";
    } else {
      $results = $palindrome . "<br><b>is palindrome</b>";
    }

    $this->flash->addMessage('Results', $results);
    return $response->withRedirect("/assignment3");
  });
})->add($isAuthorized)->add($hasAccess);

$app->get('/assignment4', function (Request $request, Response $response, array $args) {
  return $this->renderer->render($response, 'assignment4.phtml', $args);
})->add($isAuthorized)->add($hasAccess);
