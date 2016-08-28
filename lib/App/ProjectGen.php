<?php

namespace App;

use Base\Controller as BaseController;
use App\Module\StaticModule;
use Component\Form;
use Component\Input\TextInput;
use Component\ComponentFactory;
use Environment;

class ProjectGen extends BaseController {
    private $form;
    private $staticModule;
    private $appNameInput;
    private $controllerNameInput;
    private $databaseNameInput;
    private $folderNameInput;
    private $instanceNameInput;
    private $filesToMigrate = [
        [ 'filename' => 'db.php',           'subpath' => 'config/',     'parse' => true ],
        [ 'filename' => 'paths.php',        'subpath' => 'config/',     'parse' => false ],
        [ 'filename' => 'Controller.php',   'subpath' => 'lib/App/',    'parse' => true ],
        [ 'filename' => 'base.sql',         'subpath' => 'sql/',        'parse' => false ],
        [ 'filename' => 'bootstrap.php',    'subpath' => '',            'parse' => false ],
        [ 'filename' => 'index.php',        'subpath' => '',            'parse' => true ],
        [ 'filename' => '.gitignore',       'subpath' => '',            'parse' => false ]
    ];

    public function __construct() {
        parent::__construct();
        $this->presenter->addStylesheet('css/form.css');
        $this->form = $formComponent = ComponentFactory::getComponent(Form::class);
        $this->appNameInput = $this->getTextInput("app_name", "New application name");
        $this->controllerNameInput = $this->getTextInput('controller_name', 'New controller name');
        $this->databaseNameInput = $this->getTextInput('db_name', 'New database name');
        $this->folderNameInput = $this->getTextInput('folder_name', 'New folder name');
        $this->instanceNameInput = $this->getTextInput('instance_name', 'app instance variable name');
        $this->staticModule = new StaticModule();
        $contentComponent = $this->staticModule->getStaticComponent('index');
        $this->form->addChild($contentComponent);
    }

    public function run() {
        if ($this->action != 'create')
            $this->presenter->addChild($this->form);
        parent::run();
    }

    protected function actionIndex() {
        $this->form->addChild($this->appNameInput);
        $this->form->submitText = "Continue";
        $this->form->action = '?action='.'setupApp';
    }

    protected function actionSetupApp() {
        $contentComponent = $this->staticModule->getStaticComponent('setup');

        $appName = $this->appNameInput->receive();
        $controllerName = $this->getControllerName($appName);
        $databaseName = $folderName = $this->getMachineIdentifier($appName);
        $instanceName = $this->getInstanceName($appName);

        $this->controllerNameInput->value   = $controllerName;
        $this->databaseNameInput->value     = $databaseName;
        $this->folderNameInput->value       = $folderName;
        $this->instanceNameInput->value     = '$'.$instanceName;

        $this->form->submitText = "Confirm";
        $this->form->addChild($contentComponent);
        $this->form->addChild($this->appNameInput);
        $this->form->addChild($this->controllerNameInput);
        $this->form->addChild($this->databaseNameInput);
        $this->form->addChild($this->folderNameInput);
        $this->form->addChild($this->instanceNameInput);
        $this->form->action = '?action='.'create';
    }

    protected function actionCreate() {
        $localPath = Environment::getConfig('LOCAL_PATH');
        $rootPath = $localPath."../";
        $controllerName = $this->controllerNameInput->receive();
        $databaseName = $this->databaseNameInput->receive();
        $folderName = $this->folderNameInput->receive();
        $instanceName = $this->instanceNameInput->receive();

        $appPath = $rootPath.$folderName.'/';
        $this->createDirectoryStructure($appPath);
        $this->copyNewFiles($localPath, $appPath, $controllerName);
        $this->replaceNames($appPath, $controllerName, $databaseName, $instanceName);
        $this->renameController($appPath, $controllerName);
        $this->createDatabase($databaseName);
        echo "Application stub generation completed. <br />";
    }

    private function getTextInput($name, $label) {
        $textInput = ComponentFactory::getComponent(TextInput::class);
        $textInput->name = $name;
        $textInput->label = $label;
        return $textInput;
    }

    private function getControllerName($appName) {
        $parts = explode(" ", $appName);
        foreach($parts as &$part) {
            $part = ucfirst($part);
        }
        return implode('', $parts);
    }

    private function getMachineIdentifier($appName) {
        $parts = explode(" ", $appName);
        foreach($parts as &$part) {
            $part = strtolower($part);
        }
        return implode('_', $parts);
    }

    private function getInstanceName($appName) {
        $parts = explode(" ", $appName);
        $first = true;
        foreach($parts as &$part) {
            if ($first) {
                $first = false;
                $part = lcfirst($part);
            }
            else $part = ucfirst($part);
        }
        return implode('', $parts);
    }

    private function createDirectoryStructure($appPath) {
        $directories = [
            '',
            'config',
            'lib',
            'lib/App',
            'sql',
            'sql/upgrade',
            'templates'
        ];

        foreach($directories as $directory) {
            if (mkdir($appPath.$directory))
                echo "Directory $directory succesfully created. <br />";
        }
    }

    private function copyNewFiles($localPath, $appPath, $controllerName) {
        foreach($this->filesToMigrate as $file) {
            $source = $localPath . 'templates/output/' . $file['subpath'] . $file['filename'];
            $target = $appPath .  $file['subpath'] . $file['filename'];
            if (copy($source, $target)) {
                echo "$target created. <br />";
            }
        }
    }

    private function replaceNames($appPath, $controllerName, $databaseName, $instanceName) {
        foreach ($this->filesToMigrate as $file) {
            if (!$file['parse']) continue;
            $filePath = $appPath . $file['subpath'] . $file['filename'];
            $data = file_get_contents($filePath);
            $data = str_replace('%CONTROLLERNAME%', $controllerName, $data);
            $data = str_replace('%DATABASENAME%', $databaseName, $data);
            $data = str_replace('%INSTANCENAME%', $instanceName, $data);
            file_put_contents($filePath, $data);
            echo "Custom names replaced in $filePath...<br/>";
        }
    }

    private function renameController($appPath, $controllerName) {
        $source = $appPath.'lib/App/Controller.php';
        $target = $appPath.'lib/App/'.$controllerName.'.php';
        if (rename($source, $target))
            echo "Controller file sucesfully renamed <br />";
    }

    private function createDatabase($databaseName) {
        $connection = mysqli_connect(
            Environment::getConfig('DB_HOST'),
            Environment::getConfig('DB_USER'),
            Environment::getConfig('DB_PASS')
        );
        if ($connection->query("CREATE DATABASE $databaseName;"))
            echo "Database $databaseName created succesfully. <br />";
    }
}

?>