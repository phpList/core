<?php
/**
 * User: SaWey
 * Date: 6/12/13
 */

namespace phpList;


class Template {
    public $id = 0;
    public $title;
    public $template;
    public $listorder;

    public function __construct($title, $template, $listorder){
        $this->title = $title;
        $this->template = $template;
        $this->listorder = $listorder;
    }

    public static function getTemplate($id){
        $result = phpList::DB()->Sql_Fetch_Assoc_Query(sprintf(
            'SELECT * FROM %s
            WHERE id = %d',
            Config::getTableName('template'), $id));
        return Template::templateFromArray($result);
    }

    public static function templateFromArray($array){
        $template = new Template($array['title'], $array['template'], $array['listorder']);
        $template->id = $array['id'];
        return $template;
    }

    public function save(){
        if($this->id != 0){
            $this->update();
        }else{
            phpList::DB()->Sql_Query(sprintf(
                'INSERT INTO %s
                (title, template, listorder)
                VALUES("%s", "%s",%d)',
                Config::getTableName('template'), $this->title, $this->template, $this->listorder));
            $this->id = phpList::DB()->Sql_Insert_Id();
        }
    }

    public function update(){
        throw new \Exception('Not implemented yet');
    }

    public function addImage($mime, $filename, $data, $width, $height){
        $image = new TemplateImage($this->id, $mime, $filename, $data, $width, $height);
        $image->create();
    }

    public function images(){
        $images = array();
        $result = phpList::DB()->Sql_Fetch_Assoc_Query(sprintf(
            'SELECT * FROM %s
            WHERE template = %d',
            Config::getTableName('templateimage'), $this->id));
        foreach($result as $img){
            $imo = new TemplateImage($img['template'], $img['mime'], $img['filename'], $img['data'], $img['width'], $img['height']);
            $imo->id = $img['id'];
            $images[] = $imo;
        }
        return $images;
    }
}

class TemplateImage{
    public $id;
    public $template;
    public $mimetype;
    public $filename;
    public $data;
    public $width;
    public $height;

    public function __construct($template_id, $mime, $filename, $data, $width, $height){
        $this->template = $template_id;
        $this->mimetype = $mime;
        $this->filename = $filename;
        $this->data = $data;
        $this->width = $width;
        $this->height = $height;
    }

    public function create(){
        phpList::DB()->Sql_Query(sprintf(
            'INSERT INTO %s
            (template, mimetype, filename, data, width, height)
            VALUES(%d, "%s", "%s", "%s", %d, %d)',
            Config::getTableName('templateimage'), $this->template, $this->mimetype, $this->filename, $this->data, $this->width, $this->height));
        $this->id = phpList::DB()->Sql_Insert_Id();
    }

    public function delete(){
        phpList::DB()->Sql_Query(sprintf(
            'DELETE FROM %s
            WHERE id = %d)',
            Config::getTableName('templateimage'), $this->id));
    }

}