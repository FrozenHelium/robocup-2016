<?php

require_once 'Model.php';
require_once 'View.php';
require_once 'Controller.php';

function to_camel_case($str) {
    // Split string in words.
    $words = explode('_', strtolower($str));

    $return = '';
    foreach ($words as $word) {
        $return .= ucfirst(trim($word));
    }
    return $return;
}

class Page
{
    private $model;
    private $view;
    private $controller;
    private $method;
    private $method_name;

    public function __construct()
    {
        $this->model = new Model();
    }

    public function set_template($template_file_name)
    {
        $this->view = new View($this->model, $template_file_name);
    }

    public function get_model()
    {
        return $this->model;
    }

    public function set_controller($controller)
    {
        $this->controller = $controller;
    }

    public function set_method($method)
    {
        $this->method = strtolower($method);
    }

    public function set_method_name($method_name)
    {
        $this->method_name = $method_name;
    }

    public function generate()
    {
        if( $this->controller )
        {
            if (! $this->method )
                $this->method = "get";

            $action = $this->method;
            if ( $this->method_name )
                $action = $action . "_" . $this->method_name;

            $this->view = $this->controller->$action();
        }
        else if (! $this->view )
        {
            $this->set_template('__default_template.html');
        }
        $this->view->render();
    }
}

 ?>