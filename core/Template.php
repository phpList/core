<?php
/**
 * User: SaWey
 * Date: 6/12/13
 */

namespace phpList;

/**
 * Class Template
 * @package phpList
 */
class Template
{
    public $id = 0;
    public $title;
    public $template;
    public $listorder;

    /**
     * @param $title
     * @param $template
     * @param $listorder
     */
    public function __construct($title, $template, $listorder)
    {
        $this->title = $title;
        $this->template = stripslashes($template);
        $this->listorder = $listorder;
    }

    /**
     * Get template with given id from database, returns false when it does not exist
     * @param $id
     * @return bool|Template
     */
    public static function getTemplate($id)
    {
        $result = phpList::DB()->fetchAssocQuery(
            sprintf(
                'SELECT * FROM %s
                WHERE id = %d',
                Config::getTableName('template'),
                $id
            )
        );
        if(!empty($result)){
            return Template::templateFromArray($result);
        }else{
            return false;
        }
    }

    /**
     * Create a Template object from database values
     * @param $array
     * @return Template
     */
    public static function templateFromArray($array)
    {
        $template = new Template($array['title'], $array['template'], $array['listorder']);
        $template->id = $array['id'];
        return $template;
    }

    /**
     * Save template to databse, update when it already exists
     */
    public function save()
    {
        if ($this->id != 0) {
            $this->update();
        } else {
            phpList::DB()->query(
                sprintf(
                    'INSERT INTO %s
                    (title, template, listorder)
                    VALUES("%s", "%s",%d)',
                    Config::getTableName('template'),
                    $this->title,
                    addslashes($this->template),
                    $this->listorder
                )
            );
            $this->id = phpList::DB()->insertedId();
        }
    }

    /**
     * Update the template in the database
     */
    public function update()
    {
        $query = sprintf(
            'UPDATE %s SET
                title = "%s",
                template = "%s",
                listorder = "%s"
             WHERE id = %d',
            Config::getTableName('template', true),
            $this->title,
            addslashes($this->template),
            $this->listorder,
            $this->id
        );

        phpList::DB()->query($query);
    }

    /**
     * Add an image to this template
     * @param $mime
     * @param $filename
     * @param $data
     * @param $width
     * @param $height
     */
    public function addImage($mime, $filename, $data, $width, $height)
    {
        $image = new TemplateImage($this->id, $mime, $filename, $data, $width, $height);
        $image->save();
    }

    /**
     * Get images used in this template
     * @return array
     */
    public function getImages()
    {
        $images = array();
        $result = phpList::DB()->fetchAssocQuery(
            sprintf(
                'SELECT * FROM %s
                WHERE template = %d',
                Config::getTableName('templateimage'),
                $this->id
            )
        );
        foreach ($result as $img) {
            $imo = new TemplateImage($img['template'], $img['mime'], $img['filename'], $img['data'], $img['width'], $img['height']);
            $imo->id = $img['id'];
            $images[] = $imo;
        }
        return $images;
    }
}

/**
 * Class TemplateImage
 * @package phpList
 */
class TemplateImage
{
    public $id;
    public $template;
    public $mimetype;
    public $filename;
    public $data;
    public $width;
    public $height;

    /**
     * Create new image
     * @param $template_id
     * @param $mime
     * @param $filename
     * @param $data
     * @param $width
     * @param $height
     */
    public function __construct($template_id, $mime, $filename, $data, $width, $height)
    {
        $this->template = $template_id;
        $this->mimetype = $mime;
        $this->filename = $filename;
        $this->data = $data;
        $this->width = $width;
        $this->height = $height;
    }

    /**
     * Save image to database
     */
    public function save()
    {
        phpList::DB()->query(
            sprintf(
                'INSERT INTO %s
                (template, mimetype, filename, data, width, height)
                VALUES(%d, "%s", "%s", "%s", %d, %d)',
                Config::getTableName('templateimage'),
                $this->template,
                $this->mimetype,
                $this->filename,
                $this->data,
                $this->width,
                $this->height
            )
        );
        $this->id = phpList::DB()->insertedId();
    }

    /**
     * Remove this image from database
     */
    public function delete()
    {
        phpList::DB()->query(
            sprintf(
                'DELETE FROM %s
                WHERE id = %d)',
                Config::getTableName('templateimage'),
                $this->id
            )
        );
    }

}