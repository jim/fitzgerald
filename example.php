<?php

    include('lib/fitzgerald.php');

    class ApplicationWithLogin extends Fitzgerald {

        // Redefine the constructor to setup the app
        public function __construct($options=array()) {
            session_set_cookie_params(3600);
            parent::__construct($options);
        }

        // Basic get request
        public function get_index() {
            return $this->render('index');
        }

        // Login/logout
        public function get_logout() {
            $this->logout();
            return $this->redirect('/login');
        }

        public function post_login() {
            if ($this->login($this->request->username, $this->request->password)) {
                return $this->redirect('/secret');
            } else {
                $this->error = 'Invalid username or password';
                return $this->redirect('/login');
            }
        }

        public function get_secret($page) {
            $secretMessage = 'Psst!';
            return $this->render($page, compact('secretMessage'));
        }

        // before filters

        protected function verify_user() {
            if (is_null($this->session->user) || @$this->isValidUser($this->session->user)) {
                return $this->redirect('/login');
            }
        }

        // Helper methods

        private function isLoggedIn() {
            if (!is_null($this->session->user) && $this->isValidUser($this->session->user)) {
                return true;
            } else {
                $this->logout();
                return false;
            }
        }

        private function isValidUser($username) {
            return $username == 'frank';
        }

        private function login($username, $password) {
            return $username == 'frank' && $password == 'sinatra';
        }

        private function logout() {
            $this->session->user = null;
            session_destroy();
        }

    }

    // Layout is the only option right now, but you can add your own via subclassing
    $app = new ApplicationWithLogin(array('layout' => 'login'));

    // Define a before filter to be executed before one or more actions
    $app->before('get_secret|another_action', 'verify_user');

    // Basic mappings specify which function is called for a matching URL
    $app->get('/', 'get_index');
    $app->post('/login', 'post_login');

    // You can use placeholders in the URL that will be mapped to the specified function's arguments
    // The optional third argument can be an array of regexs that the url must match for each placeholder
    $app->get('/secret/:page', 'get_secret', array('page' => 'one|two|three'));

    $app->run();
?>