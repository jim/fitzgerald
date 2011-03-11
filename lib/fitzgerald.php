<?php

    // This is the only file you really need! The directory structure of this repo is a suggestion,
    // not a requirement. It's your app.


    /*  Fitzgerald - a single file PHP framework
     *  (c) 2010 Jim Benton and contributors, released under the MIT license
     *  Version 0.3
     */

    class Template {
        private $fileName;
        private $root;
        public function __construct($root, $fileName) {
            $this->fileName = $fileName;
            $this->root = $root;
        }
        public function render($locals) {
            extract($locals);
            ob_start();
            include(realpath($this->root . 'views/' . $this->fileName . '.php'));
            return ob_get_clean();
        }
    }

    class Url {
        private $url;
        private $method;
        private $conditions;

        public $params = array();
        public $match = false;

        public function __construct($httpMethod, $url, $conditions=array(), $mountPoint) {

            $requestMethod = $_SERVER['REQUEST_METHOD'];
            $requestUri = str_replace($mountPoint, '', preg_replace('/\?.+/', '', $_SERVER['REQUEST_URI']));

            $this->url = $url;
            $this->method = $httpMethod;
            $this->conditions = $conditions;

            if (strtoupper($httpMethod) == $requestMethod) {

                $paramNames = array();
                $paramValues = array();

                preg_match_all('@:([a-zA-Z]+)@', $url, $paramNames, PREG_PATTERN_ORDER);                    // get param names
                $paramNames = $paramNames[1];                                                               // we want the set of matches
                $regexedUrl = preg_replace_callback('@:[a-zA-Z_\-]+@', array($this, 'regexValue'), $url);     // replace param with regex capture
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
                return '([a-zA-Z0-9_\-]+)';
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
            $this->subject[$key] = $value;
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

        private $mappings = array(), $before_filters = Array(), $after_filters = Array();
        protected $options;
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
            echo $this->render('500', compact('number', 'message', 'file', 'line'));
            die();
        }

        public function show404() {
            header("HTTP/1.0 404 Not Found");
            echo $this->render('404');
            die();
        }

        public function get($url, $methodName, $conditions = array()) {
           $this->event('get', $url, $methodName, $conditions);
        }

        public function post($url, $methodName, $conditions = array()) {
           $this->event('post', $url, $methodName, $conditions);
        }

        public function put($url, $methodName, $conditions = array()) {
           $this->event('put', $url, $methodName, $conditions);
        }

        public function delete($url, $methodName, $conditions = array()) {
           $this->event('delete', $url, $methodName, $conditions);
        }

        public function before($methodName, $filterName) {
            $this->push_filter($this->before_filters, $methodName, $filterName);
        }

        public function after($methodName, $filterName) {
            $this->push_filter($this->after_filters, $methodName, $filterName);
        }

        protected function push_filter(&$arr_filter, $methodName, $filterName) {
            if (!is_array($methodName)) {
                $methodName = explode('|', $methodName);
            }

            for ($i = 0; $i < count($methodName); $i++) {
                $method = $methodName[$i];
                if (!isset($arr_filter[$method])) {
                    $arr_filter[$method] = array();
                }
                array_push($arr_filter[$method], $filterName);
            }
        }

        protected function run_filter($arr_filter, $methodName) {
            if(isset($arr_filter[$methodName])) {
                for ($i=0; $i < count($arr_filter[$methodName]); $i++) {
                    $return = call_user_func(array($this, $arr_filter[$methodName][$i]));

                    if(!is_null($return)) {
                        return $return;
                    }
                }
            }
        }

        public function run() {
            echo $this->processRequest();
        }

        protected function redirect($path) {
            $protocol = empty($_SERVER['HTTPS']) ? 'http' : 'https';
            $host = (preg_match('%^http://|https://%', $path) > 0) ? '' : "$protocol://" . $_SERVER['HTTP_HOST'];
            $uri = is_string($this->options->mountPoint) ? $this->options->mountPoint : '';
            if (!empty($this->error)) {
              $this->session->error = $this->error;
            }
            if (!empty($this->success)) {
              $this->session->success = $this->success;
            }
            header("Location: $host$uri$path");
            return false;
        }

        protected function render($fileName, $variableArray=array()) {
            $variableArray['options'] = $this->options;
            $variableArray['request'] = $this->request;
            $variableArray['session'] = $this->session;
            if(isset($this->error)) {
                $variableArray['error'] = $this->error;
            }
            if(isset($this->success)) {
                $variableArray['success'] = $this->success;
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

        protected function execute($methodName, $params) {
            $return = $this->run_filter($this->before_filters, $methodName);
            if (!is_null($return)) {
              return $return;
            }

            if ($this->session->error) {
                $this->error = $this->session->error;
                $this->session->error = null;
            }

            if ($this->session->success) {
                $this->success = $this->session->success;
                $this->session->success = null;
            }

            $reflection = new ReflectionMethod(get_class($this), $methodName);
            $args = array();

            foreach($reflection->getParameters() as $param) {
                if(isset($params[$param->name])) {
                    $args[$param->name] = $params[$param->name];
                }
                else if($param->isDefaultValueAvailable()) {
                    $args[$param->name] = $param->getDefaultValue();
                }
            }

            $response = $reflection->invokeArgs($this, $args);

            $return = $this->run_filter($this->after_filters, $methodName);
            if (!is_null($return)) {
              return $return;
            }

            return $response;
        }

        protected function event($httpMethod, $url, $methodName, $conditions=array()) {
            if (method_exists($this, $methodName)) {
                array_push($this->mappings, array($httpMethod, $url, $methodName, $conditions));
            }
        }

        protected function root() {
            if($root = $this->options->root)
              return $root;
            else
              return dirname(__FILE__) . '/../';
        }

        protected function path($path) {
            return $this->root() . $path;
        }

        protected function processRequest() {
            $charset = (is_string($this->options->charset)) ? ";charset={$this->options->charset}" : "";
            header("Content-type: text/html" . $charset);

            for($i = 0; $i < count($this->mappings); $i++) {
                $mapping = $this->mappings[$i];
                $mountPoint = is_string($this->options->mountPoint) ? $this->options->mountPoint : '';
                $url = new Url($mapping[0], $mapping[1], $mapping[3], $mountPoint);

                if($url->match) {
                    return $this->execute($mapping[2], $url->params);
                }
            }

            return $this->show404();
        }
    }
