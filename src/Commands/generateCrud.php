<?php

namespace Sysfast\Commands;

use Illuminate\Console\Command;
use View;
use Storage;
use File;

class generateCrud extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'generateCrud
                            {option : migration , model , controller, form ,view , all}
                            {name :  Model name}
                            {json :  Json file}';


    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'CRUD Generation';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */

    public $fieldType = array( 'integer' => 'integer',
                        'increments' => 'increments',
                        'bigIncrements' => 'bigIncrements',
                        'string' => 'string',
                        'char' => 'char',
                        'text' => 'text',
                        'enum' => 'enum',
                        'bigInteger' => 'bigInteger',
                        'boolean' => 'boolean',
                        'date' => 'date',
                        'decimal' => 'decimal',
                        'double' => 'double',
                        'float' => 'float',
                        'longText' => 'longText',
                        'smallInteger' => 'smallInteger',
                        'tinyInteger' => 'tinyInteger',
                        'unsignedBigInteger' => 'unsignedBigInteger',
                        'unsignedDecimal' => 'unsignedDecimal',
                        'unsignedInteger' => 'unsignedInteger',
                        'unsignedMediumInteger' => 'unsignedMediumInteger',
                        'unsignedSmallInteger' => 'unsignedSmallInteger',
                        'unsignedTinyInteger' => 'unsignedTinyInteger',
                    );

    public function getJsonFile($path){
        $json_str = file_get_contents(base_path($path));
        $data = json_decode($json_str);
        return $data;

    }

    public function migrationFields($json){

        $fields = array();
        if($json->options->id == true)
            array_push($fields,"id();");

        foreach($json->fields as $index=>$d){
            $options="";
            $params="'".$d->name."'";

            foreach($d as $key=>$oo){

                if($key == "length")
                    $params=$params.",".$d->length;

                if($key == "digits")
                    $params=$params.",".$d->digits;

                if($key == "decimals")
                    $params=$params.",".$d->decimals;

                if($key == "list")
                    $params=$params.",['".implode("','",$d->list)."']";

                if($key == "nullable" && $d->nullable == true)
                    $options=$options."->nullable()";

                if( $key == "default" ){
                    if(gettype($d->default) == "string")
                        $options=$options."->default('".$d->default."')";
                    else
                        $options=$options."->default($d->default)";
                }
            }

            $f = $this->fieldType[$d->type]."(".$params.")".$options;
            $f=$f.";";
            array_push($fields,$f);
        }
        if(isset($json->options->timestamps) &&$json->options->timestamps == true)
            array_push($fields,"timestamps();");
        return $fields;


    }


    public function getImports($foreigns){

        $classes = array();
        foreach($foreigns as $foreign){
            array_push($classes,$foreign->fkClass);
        }
        return array_unique($classes);
    }

    public function controllerFields($json){
            $fields = array();

            foreach($json->fields as $index=>$d){
                $f = "";
                if(isset($d->required) && $d->required == true){
                        $f=$f."'".$d->name."' => 'required',";
                        array_push($fields,$f);
                }
            }
            return $fields;
    }



    public function createController($name,$json){

        $validations=$this->controllerFields($json);
        $imports=$this->getImports($json->relations);


        $LCName=strtolower($name);
        $LCPName=$LCName."s";

        $folder=[ 'root' => base_path('app/Http/Controllers')];

        $driver = Storage::createLocalDriver($folder);

        $template = View::make('templates.controller',['name' => $name,'LCName' =>$LCName,'LCPName'=> $LCPName,'validations' =>$validations,'fields'=>$json->fields, 'foreigns'=>$json->relations,'imports'=>$imports])->render();
        $template = "<?php \n".$template."\n ?>";
        $driver->put($name."Controller.php",$template);
    }

    public function createModel($name,$json){

        $imports=$this->getImports($json->relations);

        $folder=[ 'root' => base_path('app/Models')];

        $driver = Storage::createLocalDriver($folder);

        $fields = array();
        $files = array();

        foreach($json->fields as $index=>$d){
            if(isset($d->form) && $d->form == true)
                array_push($fields,$d->name);
            if(isset($d->file))
                array_push($files,$d->name);

        }

        $useTimestamp = false;
        if(isset($json->options->timestamp) && $json->options->timestamp == true)
            $useTimestamp=true;

        $template = View::make('templates.model',['name' => $name, 'fields'=> $fields,'foreigns'=> $json->relations,'imports'=>$imports, "useTimestamp" => $useTimestamp, "files" =>$files ])->render();
        $template = "<?php \n".$template."\n ?>";
        $driver->put($name.".php",$template);
    }


    public function createMigration($name,$fields){

        $folder=[ 'root' => base_path('database/migrations')];
        $driver = Storage::createLocalDriver($folder);

        $template = View::make('templates.migration',['name' => $name,'fields'=>$fields])->render();
        $template = "<?php \n".$template."\n ?>";
        $driver->put(date("Y_m_d_u")."_create_".strtolower($name)."s_table.php",$template);
    }


    public function createViewIndex($name,$json){

        $LCPName=strtolower($name)."s";

        $viewFolder = base_path('resources/views/'.$LCPName);
        if (!is_dir($viewFolder))
            mkdir($viewFolder);

        $folder=[ 'root' => $viewFolder];
        $driver = Storage::createLocalDriver($folder);

        $template = View::make('templates.index',['name' => $name,'LCPName' => $LCPName,'fields' => $json->fields])->render();
        $driver->put("index.blade.php",$template);
    }

    public function multimediaExists($json){

        foreach($json->fields as $index=>$d){
            if(isset($d->file)){
                    return true;
            }
        }
        return false;
    }

    public function createViewCreate($name,$json,$multimedia){


        $LCName=strtolower($name);
        $LCPName=strtolower($name)."s";

        $viewFolder = base_path('resources/views/'.$LCPName);
        if (!is_dir($viewFolder))
            mkdir($viewFolder);

        $folder=[ 'root' => $viewFolder];
        $driver = Storage::createLocalDriver($folder);


        $template = View::make('templates.create',['name' => $name,'LCName' =>$LCName,'LCPName'=> $LCPName,'fields' => $json->fields,"foreigns" => $json->relations,"multimedia"=>$multimedia])->render();
        $driver->put("create.blade.php",$template);
    }


    public function createViewEdit($name,$json,$multimedia){

        $LCName=strtolower($name);
        $LCPName=$LCName."s";

        $viewFolder = base_path('resources/views/'.$LCPName);
        if (!is_dir($viewFolder))
            mkdir($viewFolder);

        $folder=[ 'root' => $viewFolder];
        $driver = Storage::createLocalDriver($folder);

        $template = View::make('templates.edit',['name' => $name,'LCName'=>$LCName,'LCPName'=>$LCPName,'fields' => $json->fields,"foreigns" => $json->relations,"multimedia"=>$multimedia])->render();
        $driver->put("edit.blade.php",$template);
    }

    public function loadFieldForm($name,$type, $choices=NULL, $relation=NULL){

        if( $type == "select"){
            return View::make('templates.fields_form.select',['name' => $name,'choices' => $choices])->render();
        }


        if( $type == "image"){
            $field="file";
            return View::make('templates.fields_form.image',['name' => $name,'field'=>$field])->render();
        }

        if( $relation == 'belongsTo'){
                return View::make('templates.fields_form.select2',['name' => $name])->render();
        }


        if( $type == "integer" || $type== "increments" || $type== "bigIncrements" || $type=="bigInteger" || $type=="tinyInteger" ||  $type=="smallInteger" || $type=="unsignedBigInteger" || $type=="unsignedInteger" || $type=="unsignedMediumInteger"
                               || $type=="unsignedSmallInteger"  ||  $type=="unsignedTinyInteger"  ){

                $field="number";
                $step=1;
                return View::make('templates.fields_form.text',['name' => $name,'field'=>$field,'step'=>$step])->render();
        }


        if( $type == "decimal" || $type== "double" || $type== "float" || $type=="unsignedDecimal" ){

                $field="number";
                $step="any";
                return View::make('templates.fields_form.text',['name' => $name,'field'=>$field,'step'=>$step])->render();
        }


        if( $type == "string" || $type == "char" ){
                $field="text";
                return View::make('templates.fields_form.text',['name' => $name,'field'=>$field])->render();
        }

        if( $type == "date"){
            $field="date";
            return View::make('templates.fields_form.text',['name' => $name,'field'=>$field])->render();
        }


        if( $type == "text" || $type == "longText" ){
            return View::make('templates.fields_form.textarea',['name' => $name])->render();
        }

        if( $type == "enum"){
            return View::make('templates.fields_form.radio',['name' => $name,'choices' => $choices])->render();
        }

        if( $type == "boolean"){
            return View::make('templates.fields_form.checkbox',['name' => $name])->render();
        }



    }

    public function relationExists($relations,$field){
        foreach($relations as $relation){
            if($relation->field == $field)
                return $relation;
        }
        return NULL;
    }

    public function createViewForm($name,$json){

        $LCName=strtolower($name);
        $LCPName=$LCName."s";

        $viewFolder = base_path('resources/views/'.$LCPName);
        if (!is_dir($viewFolder))
            mkdir($viewFolder);

        $folder=[ 'root' => $viewFolder];
        $driver = Storage::createLocalDriver($folder);
        $template = '<div class="row">'."\n";
        $col1='<div class="col-md-6">'."\n";
        $col2='<div class="col-md-6">'."\n";
        foreach($json->fields as $index=>$d){
            //var_dump(isset($d->form));
            if(isset($d->form) && $d->form == true){
                if(!isset($d->list))$d->list=NULL;
                $relation=$this->relationExists($json->relations,$d->name);
                if($relation != NULL)
                    $relation =  $relation->type;

                if(isset($d->file) && $d->file == "image")
                    $d->type="image";

                if(isset($d->display) && $d->display == "select")
                    $d->type="select";

                if($index % 2 == 0)
                    $col1=$col1.$this->loadFieldForm($d->name,$d->type,$d->list,$relation)."\n";
                else
                    $col2=$col2.$this->loadFieldForm($d->name,$d->type,$d->list,$relation)."\n";
            }
        }

        $col1 =$col1.'</div>'."\n";
        $col2 =$col2.'</div>'."\n";
        $template=$template.$col1;
        $template=$template.$col2;
        $template=$template.'</div>'."\n";
        $driver->put("form.blade.php",$template);
    }

    /*public function deleteGen($name){

        $folder=[ 'root' => base_path('app/Models')];
        $driver = Storage::createLocalDriver($folder);
        $driver->delete($name.".php");
    }*/



    public function handle()
    {


        //echo($this->argument('option')."\n");
        //echo(getcwd()."\n");
        //echo(dirname(__FILE__)."\n");

        if( $this->argument('option') == "controller"){
            $json=$this->getJsonFile($this->argument('json'));
            $this->createController($this->argument('name'),$json);
        }

        if( $this->argument('option') == "model"){
            $json=$this->getJsonFile($this->argument('json'));
            $this->createModel($this->argument('name'),$json);
        }


        if( $this->argument('option') == "json"){
            $json=$this->getJsonFile($this->argument('json'));
            //var_dump($json);
            //this->migrationFields($json);

        }

        if( $this->argument('option') == "migration"){

            $json=$this->getJsonFile($this->argument('json'));
            $fields=$this->migrationFields($json);
            $this->createMigration($this->argument('name'),$fields);
        }

        if( $this->argument('option') == "view"){
            $json=$this->getJsonFile($this->argument('json'));

            $multimedia = $this->multimediaExists($json);

            $this->createViewIndex($this->argument('name'),$json);
            $this->createViewCreate($this->argument('name'),$json,$multimedia);

            $this->createViewEdit($this->argument('name'),$json,$multimedia);

        }

        if( $this->argument('option') == "form"){
            $json=$this->getJsonFile($this->argument('json'));
            $this->createViewForm($this->argument('name'),$json);
            //$this->createViewCreate($this->argument('name'),$json);

        }

        if( $this->argument('option') == "all"){
            $json=$this->getJsonFile($this->argument('json'));
            $multimedia = $this->multimediaExists($json);


            $fields=$this->migrationFields($json);
            $this->createMigration($this->argument('name'),$fields);

            $this->createModel($this->argument('name'),$json);

            $this->createController($this->argument('name'),$json);

            $this->createViewForm($this->argument('name'),$json);

            $this->createViewIndex($this->argument('name'),$json);

            $this->createViewCreate($this->argument('name'),$json,$multimedia);

            $this->createViewEdit($this->argument('name'),$json,$multimedia);

            echo("\n");
            echo("Add these lines to your web.php :\n");
            echo("\n");
            echo("use App\Http\Controllers\\".$this->argument('name')."Controller;\n");
            echo("Route::middleware('auth')->resource('".strtolower($this->argument('name'))."s',".$this->argument('name')."Controller::class);\n");
            echo("\n");
        }

        /*if( $this->argument('option') == "delete"){
            $this->deleteGen($this->argument('name'));

        }*/


        return 0;
    }
}

