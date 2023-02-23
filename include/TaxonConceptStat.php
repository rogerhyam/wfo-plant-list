<?php

class TaxonConceptStat{

    public string $id;
    public string $title;
    public int $value;

    public function __construct($id, $title, $value){
        $this->id = $id;
        $this->title = $title;
        $this->value = $value;
    }

}