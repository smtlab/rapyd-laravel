<?php

namespace Zofe\Rapyd\DataForm\Field;

use Illuminate\Support\Facades\Form;
use Illuminate\Support\Facades\Input;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Session;
use MyProject\Proxies\__CG__\stdClass;
use Zofe\Rapyd\Rapyd;

class Tags extends Field {

    public $type = "tags";
    public $css_class = "autocompleter";

    public $remote;
    public $separator = "&nbsp;";
    public $local_options;
    
    public $record_id;
    public $record_label;

    public $must_match = false;
    public $auto_fill = false;
    public $parent_id = '';
    
    public $min_chars = '2';


    
    public function options($options)
    {
        $this->is_local = true;
        parent::options($options);
        foreach ($options as $key=>$value)
        {
            $row = new \stdClass();
            $row->key = $key;
            $row->value = $value;
            $this->local_options[] =$row;
        }
        return $this;
        
    }

    function getValue()
    {
        parent::getValue();
        $this->values = explode($this->serialization_sep, $this->value);
        
        if (count($this->local_options)) {
            $description_arr = array();
            $this->fill_tags = "";
            foreach ($this->options as $value => $description) {
                if (in_array($value, $this->values)) {
                    $description_arr[] = $description;


                    $row = new \stdClass();
                    $row->key = $value;
                    $row->value = $description;
                    $this->fill_tags .= "
                      $('#{$this->name}').tagsinput('add', ".json_encode($row).");";
                }
            }
            $this->description = implode($this->separator, $description_arr);
        }  elseif ($this->relation != null) {

            $related = $this->relation->get();
            $name = $this->rel_field;
            $key = $this->record_id;
            $this->fill_tags = "";
            if (count($related)){
                foreach($related as $item) {
                    $row = new \stdClass();
                    $row->$key = $item->$key;
                    $row->$name = $item->$name;
                    $this->fill_tags .= "
                      $('#{$this->name}').tagsinput('add', ".json_encode($row).");";
                }
            }
        }

    }

    function getNewValue()
    {
        parent::getNewValue();
       $this->new_value = str_replace(",",$this->serialization_sep, $this->new_value);
    }
    

    public function remote($record_label = null, $record_id = null, $remote = null)
    {
        $this->record_label = ($record_label!="") ? $record_label : $this->db_name ;
        $this->record_id = ($record_id!="") ? $record_id : $this->db_name ;
        if ($remote!="") {
            $this->remote = $remote;
            if (is_array($record_label))
            {
                $this->record_label = current($record_label);
            }
            if ($this->rel_field!= "")
            {
                $this->record_label = $this->rel_field;
            }
        } else {

            $data["entity"] = get_class($this->relation->getRelated());
            $data["field"]  = $record_label;
            if (is_array($record_label))
            {
                $this->record_label = $this->rel_field;
            }
            $hash = substr(md5(serialize($data)), 0, 12);
            Session::put($hash, $data);

            $this->remote = route('rapyd.remote', array('hash'=> $hash));
        }
        
    }


    public function build()
    {
        $output = "";
        //typeahead
        Rapyd::css('packages/zofe/rapyd/assets/autocomplete/autocomplete.css');
        Rapyd::js('packages/zofe/rapyd/assets/autocomplete/typeahead.bundle.min.js');
        Rapyd::js('packages/zofe/rapyd/assets/template/handlebars.js');
        //tagsinput
        Rapyd::css('packages/zofe/rapyd/assets/autocomplete/bootstrap-tagsinput.css');
        Rapyd::js('packages/zofe/rapyd/assets/autocomplete/bootstrap-tagsinput.min.js');

        unset($this->attributes['type']);

        
        if (parent::build() === false) return;


        switch ($this->status)
        {
            case "disabled":
            case "show":
                if ( (!isset($this->value)) )
                {
                    $output = $this->layout['null_label'];
                } else {
                    $output = $this->description;
                }
                break;

            case "create":
            case "modify":

                $output  =  Form::text($this->name, '', array_merge($this->attributes, array('id'=>"".$this->name)))."\n";
                if ($this->remote) 
                {
                    $script = <<<acp
    
                    $('#{$this->name}').tagsinput({
                      itemValue: '{$this->record_id}',
                      itemText: '{$this->record_label}'
                    });
                    {$this->fill_tags}
                    
                    var blod_{$this->name} = new Bloodhound({
                        datumTokenizer: Bloodhound.tokenizers.obj.whitespace('{$this->name}'),
                        queryTokenizer: Bloodhound.tokenizers.whitespace,
                        remote: '{$this->remote}?q=%QUERY'
                    });
                    blod_{$this->name}.initialize();
                
                    $('#{$this->name}').tagsinput('input').typeahead(null, {
                        name: '{$this->name}',
                        displayKey: '{$this->record_label}',
                        highlight: true,
                        minLength: {$this->min_chars},
                        source: blod_{$this->name}.ttAdapter()
                    }).bind('typeahead:selected', $.proxy(function (obj, data) {
                        this.tagsinput('add', data);
                        this.tagsinput('input').typeahead('val', '');
                    }, $('#{$this->name}')));


acp;
    
                    $output .= Rapyd::script($script);

                    
                } elseif (count($this->options)) {
                   
                    
                    $options = json_encode($this->local_options);

                    //options
                    $script = <<<acp

                    var {$this->name}_options = {$options};


                    $('#{$this->name}').tagsinput({
                      itemValue: 'key',
                      itemText: 'value'
                    });
                    {$this->fill_tags}
                    
                    
                    var blod_{$this->name} = new Bloodhound({
                        datumTokenizer: Bloodhound.tokenizers.obj.whitespace('value'),
                        queryTokenizer: Bloodhound.tokenizers.whitespace,
                        local: {$this->name}_options
                    });


                    blod_{$this->name}.initialize();                 
                    $('#{$this->name}').tagsinput('input').typeahead({
                         hint: true,
                         highlight: true,
                         minLength: {$this->min_chars}
                    },
                    {
                        name: '{$this->name}',
                        displayKey: 'value',
                        source: blod_{$this->name}.ttAdapter()
                    }).bind('typeahead:selected', $.proxy(function (obj, data) {
                        this.tagsinput('add', data);
                        this.tagsinput('input').typeahead('val', '');
                    }, $('#{$this->name}')));

acp;

                    $output .= Rapyd::script($script);
                }

                break;

            case "hidden":
                $output = Form::hidden($this->db_name, $this->value);
                break;

            default:;
        }
        $this->output = "\n".$output."\n". $this->extra_output."\n";
    }

}
