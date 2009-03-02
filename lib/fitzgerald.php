<?php

    // This is the only file you really need! The directory structure of this repo is a suggestion,
    // not a requirement. It's your app.


    /*  Fitzgerald - a single file PHP framework
     *  (c) 2008 Jim Benton, released under the MIT license
     *  Version 0.2
     */

    class Template {
        private $fileName;
        private $root;
        public function __construct($root, $fileName) {
            $this->fileName = $fileName;
            $this->root = $root;
        }
        public function render($locals) {
            foreach ($locals as $local => $value) {
                $$local = $value;
            }
            ob_start();
            include($this->root . 'views/' . $this->fileName . '.php');
            return ob_get_clean();
        }
    }

    class Url {
        private $url;
        private $method;
        private $conditions;

        private $filters = array();
        public $params = array();
        public $match = false;

        public function __construct($httpMethod, $url, $conditions=array(), $mountPoint) {

            $requestMethod = $_SERVER['REQUEST_METHOD'];
            $requestUri = str_replace($mountPoint, '', $_SERVER['REQUEST_URI']);

            $this->url = $url;
            $this->method = $httpMethod;
            $this->conditions = $conditions;

            if (strtoupper($httpMethod) == $requestMethod) {

                $paramNames = array();
                $paramValues = array();

                preg_match_all('@:([a-zA-Z]+)@', $url, $paramNames, PREG_PATTERN_ORDER);                    // get param names
                $paramNames = $paramNames[1];                                                               // we want the set of matches
                $regexedUrl = preg_replace_callback('@:[a-zA-Z_]+@', array($this, 'regexValue'), $url);     // replace param with regex capture
                if (preg_match('@^' . $regexedUrl . '$@', $requestUri, $paramValues)){                      // determine match and get param values
                    array_shift($paramValues);                                                              // remove the complete text match
                    for ($i=0; $i < count($paramNames); $i++) {
                        $this->params[$paramNames[$i]] = $paramValues[$i];
                    }
                    $this->match = true;
                }
            }
        }

        private function regexValue($matches) {
            $key = str_replace(':', '', $matches[0]);
            if (array_key_exists($key, $this->conditions)) {
                return '(' . $this->conditions[$key] . ')';
            } else {
                return '([a-zA-Z0-9_]+)';
            }
        }

    }

    class ArrayWrapper {
        private $subject;
        public function __construct(&$subject) {
            $this->subject = $subject;
        }
        public function __get($key) {
            return isset($this->subject[$key]) ? $this->subject[$key] : null;
        }

        public function __set($key, $value) {
            $this->subject = $value;
            return $value;
        }
    }

    class SessionWrapper {
        public function __get($key) {
            global $_SESSION;
            return isset($_SESSION[$key]) ? $_SESSION[$key] : null;
        }

        public function __set($key, $value) {
            global $_SESSION;
            $_SESSION[$key] = $value;
            return $value;
        }
    }

    class RequestWrapper {
        public function __get($key) {
            global $_REQUEST;
            return isset($_REQUEST[$key]) ? $_REQUEST[$key] : null;
        }

        public function __set($key, $value) {
            global $_REQUEST;
            $_REQUEST[$key] = $value;
            return $value;
        }
    }

    class Fitzgerald {

        private $mappings = array();
        private $options;
        protected $session;
        protected $request;

        public function __construct($options=array()) {
            $this->options = new ArrayWrapper($options);
            session_name('fitzgerald_session');
            session_start();
            $this->session = new SessionWrapper;
            $this->request = new RequestWrapper;
            set_error_handler(array($this, 'handleError'), 2);
        }

        public function handleError($number, $message, $file, $line) {
            header("HTTP/1.0 500 Server Error");
            echo $this->render('500');
            die();
        }

        public function show404() {
            header("HTTP/1.0 404 Not Found");
            echo $this->render('404');
            die();
        }

        public function get($url, $methodName, $conditions=array()) {
           $this->event('get', $url, $methodName, $conditions);
        }

        public function post($url, $methodName, $conditions=array()) {
           $this->event('post', $url, $methodName, $conditions);
        }

        public function before($methodName, $filterName) {
            if (!is_array($methodName)) {
                $methodName = explode('|', $methodName);
            }
            for ($i = 0; $i < count($methodName); $i++) {
                $method = $methodName[$i];
                if (!isset($this->filters[$method])) {
                    $this->filters[$method] = array();
                }
                array_push($this->filters[$method], $filterName);
            }
        }

        public function run() {
            echo $this->processRequest();
        }

        protected function redirect($path) {
            $protocol = $_SERVER['HTTPS'] ? 'https' : 'http';
            $host = (preg_match('%^http://|https://%', $path) > 0) ? '' : "$protocol://" . $_SERVER['HTTP_HOST'];
            $uri = is_string($this->options->mointPoint) ? $this->options->mountPoint : '';
            $this->session->error = $this->error;
            header("Location: $host$uri$path");
            return false;
        }

        protected function render($fileName, $variableArray=array()) {
            $variableArray['session'] = $this->session;
            $variableArray['request'] = $this->request;
            if(isset($this->error)) {
                $variableArray['error'] = $this->error;
            }

            if (is_string($this->options->layout)) {
                $contentTemplate = new Template($this->root(), $fileName);              // create content template
                $variableArray['content'] = $contentTemplate->render($variableArray);   // render and store contet
                $layoutTemplate = new Template($this->root(), $this->options->layout);  // create layout template
                return $layoutTemplate->render($variableArray);                         // render layout template and return
            } else {
                $template = new Template($this->root(), $fileName);                     // create template
                return $template->render($variableArray);                               // render template and return
            }
        }

        protected function sendFile($filename, $contentType, $path) {
            header("Content-type: $contentType");
            header("Content-Disposition: attachment; filename=$filename");
            return readfile($path);
        }

        protected function sendDownload($filename, $path) {
            header("Content-Type: application/force-download");
            header("Content-Type: application/octet-stream");
            header("Content-Type: application/download");
            header("Content-Description: File Transfer");
            header("Content-Disposition: attachment; filename=$filename".";");
            header("Content-Transfer-Encoding: binary");
            return readfile($path);
        }

        private function execute($methodName, $params) {
            if (isset($this->filters[$methodName])) {
                for ($i=0; $i < count($this->filters[$methodName]); $i++) {
                    $return = call_user_func(array($this, $this->filters[$methodName][$i]));
                    if ($return) {
                        return $return;
                    }
                }
            }

            if ($this->session->error) {
                $this->error = $this->session->error;
                $this->session->error = null;
            }

            $reflection = new ReflectionMethod('Application', $methodName);
            $args = array();

            foreach ($reflection->getParameters() as $i => $param) {
                $args[$param->name] = $params[$param->name];
            }
            return call_user_func_array(array($this, $methodName), $args);
        }

        private function event($httpMethod, $url, $methodName, $conditions=array()) {
            if (method_exists($this, $methodName)) {
                array_push($this->mappings, array($httpMethod, $url, $methodName, $conditions));
            }
        }

        protected function root() {
            return dirname(__FILE__) . '/../';
        }

        protected function path($path) {
            return $this->root() . $path;
        }

        private function processRequest() {
            for ($i = 0; $i < count($this->mappings); $i++) {
                $mapping = $this->mappings[$i];
                $mountPoint = is_string($this->options->mountPoint) ? $this->options->mountPoint : '';
                $url = new Url($mapping[0], $mapping[1], $mapping[3], $mountPoint);
                if ($url->match) {
                    return $this->execute($mapping[2], $url->params);
                }
            }
            return $this->show404();
        }
    }

?>