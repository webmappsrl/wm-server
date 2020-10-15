<?php

abstract class WebmappCliAbstractCommand
{
    protected $options = array();
    private $name;

    public function __construct($argv)
    {
        // Set Name
        $name = strtolower(trim(get_class($this)));
        $name = preg_replace('/webmappcli/', '', $name);
        $name = preg_replace('/command/', '', $name);
        $this->name = $name;

        // Set command options
        if (count($argv) > 2) {
            $this->options = array_slice($argv, 2);
        }
        $this->specificConstruct();
    }

    abstract public function specificConstruct();

    abstract public function getExcerpt();

    abstract public function showHelp();

    public function execute()
    {
        if (count($this->options) > 0 && $this->options[0] == 'help') {
            $this->showHelp();
        } else {
            $this->executeNoHelp();
        }
    }

    abstract public function executeNoHelp();

    public function getName()
    {
        return $this->name;
    }

    public function getOptions()
    {
        return $this->options;
    }
}

class WebmappCliVersionCommand extends WebmappCliAbstractCommand
{
    public function specificConstruct()
    {
        return true;
    }

    public function getExcerpt()
    {
        $string = "returns webmappServer version";
        return $string;
    }

    public function showHelp()
    {
        $string = "\nThis simply command (no options needed) show the webmappServerVersion\n\n";
        echo $string;
    }

    public function executeNoHelp()
    {
        echo "\nCurrent WebmappServerVersion: XX.XX.XX\n";
        return true;
    }
}

class WebmappCliShowconfigCommand extends WebmappCliAbstractCommand
{
    public function specificConstruct()
    {
        return true;
    }

    public function getExcerpt()
    {
        $string = "shows all configuration settings.";
        return $string;
    }

    public function showHelp()
    {
        $string = "\nDisplay all configuration seettings with different sections.\n\n";
        echo $string;
    }

    public function executeNoHelp()
    {
        global $wm_config;
        echo "\n";
        foreach ($wm_config as $section => $items) {
            echo "SECTION: $section\n";
            foreach ($items as $k => $v) {
                echo " -> $k : $v\n";
            }
            echo "\n";
        }
        return true;
    }
}

class WebmappCliNewCommand extends WebmappCliAbstractCommand
{
    public function specificConstruct()
    {
        return true;
    }

    public function getExcerpt()
    {
        $string = "Creates new project structure.";
        return $string;
    }

    public function showHelp()
    {
        $string = "\nCreates new project structure.\n\n";
        echo $string;
    }

    public function executeNoHelp()
    {
        $root = $this->options[0];
        echo "\nCreating project in path $root\n";
        if (file_exists($root)) {
            throw new Exception("Can't create project: $root already exists.", 1);
        }
        $s = new WebmappProjectStructure($root);
        $s->create();
    }
}

class WebmappCliTaskCommand extends WebmappCliAbstractCommand
{
    public function specificConstruct()
    {
        return true;
    }

    public function getExcerpt()
    {
        $string = "Performs some operations on a project TASK";
        return $string;
    }

    public function showHelp()
    {
        $string = "\n 
Usage: wmcli task /root/to/project/ [subcommands]
Available subcommands:
list (default) : List all task in the project (name and type)
run            : Run all task in a project
runtask [task_name] : Run a specific task\n";
        echo $string;
    }

    public function executeNoHelp()
    {
        if (!isset($this->options[0])) {
            throw new Exception("ERROR: root to project is mandatory.", 1);
        }
        $root = $this->options[0];
        $p = new WebmappProject($root);
        $p->check();
        $action = 'list';

        $task_names = array();
        foreach ($p->getTasks() as $t) {
            $task_names[] = $t->getName();
        }

        // Check other subactions
        if (count($this->options) > 1) {
            $action = $this->options[1];
            if (!in_array($action, array('list', 'run', 'runtask'))) {
                throw new Exception("SUBACTION $action not valid: only list, run, runtask avalilable", 1);
            }
            // TODO check runtask:
        }

        // PROCESS:
        switch ($action) {
            case 'list':
                $count = 1;
                foreach ($p->getTasks() as $t) {
                    echo "TASK#$count : " . $t->getName() . " (type:" . get_class($t) . ")\n";
                    $count++;
                }
                break;
            case 'run':
                $p->process();
                break;
            case 'runtask':
                if (!isset($this->options[2])) {
                    throw new Exception("Subaction runtask needs taskname parameter", 1);
                }
                $task_name = $this->options[2];
                if (!in_array($task_name, $task_names)) {
                    throw new Exception("Taskname NOT valid, use subcommand list", 1);
                }
                $t = $p->getTaskByName($task_name);
                $t->process();
                break;
        }

    }
}

class WebmappCliServerCommand extends WebmappCliAbstractCommand
{
    public function specificConstruct()
    {
        return true;
    }

    public function getExcerpt()
    {
        $string = "Create a server instance that uses HOQU (start, stop, log)";
        return $string;
    }

    public function showHelp()
    {
        $string = "\n 
Usage: wmcli server [subcommands]
Available subcommands:
start (default) : Start a new server instance
stop [pid]      : Stop the existing server instance with the specified pid
log             : Log the active server instances\n";
        echo $string;
    }

    public function executeNoHelp()
    {
        global $wm_config;
        try {
            $server = new WebmappHoquServer($wm_config["debug"]);
            $server->run();
        } catch (WebmappExceptionParameterMandatory $e) {
            WebmappUtils::error($e->getMessage());
        }
    }
}


// class WebmappCliXXXCommand extends WebmappCliAbstractCommand {
//  public function specificConstruct() { return true; }
// 	public function getExcerpt() {
//         $string = "Excerpt";
//         return $string;
// 	}
// 	public function showHelp() {
// 		$string = "\nHelp\n\n";
//         echo $string;
// 	}
// 	public function executeNoHelp() {
// 		echo "\ncommand\n";
// 		return true;
// 	}
// }
