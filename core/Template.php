<?php
namespace phpList;

use phpList\entities\TemplateEntity;
use phpList\entities\TemplateImageEntity;

/**
 * Class Template
 * @package phpList
 */
class Template
{   
    protected $db;
    protected $config;
    protected $template_image;


    public function __construct(Config $config, helper\Database $db, TemplateImage $template_image)
    {
        $this->db = $db;
        $this->config = $config;
        $this->template_image = $template_image;
    }

    /**
     * Get template with given id from database, returns false when it does not exist
     * @param $id
     * @return bool|TemplateEntity
     */
    public function getTemplate($id)
    {
        $result = $this->db->query(
            sprintf(
                'SELECT * FROM %s
                WHERE id = %d',
                $this->config->getTableName('template'),
                $id
            )
        );
        if($result->rowCount() > 0){
            return $this->templateFromArray($result->fetch(\PDO::FETCH_ASSOC));
        }else{
            return false;
        }
    }

    /**
     * Create a Template object from database values
     * @param $array
     * @return TemplateEntity
     */
    public function templateFromArray($array)
    {
        $template = new TemplateEntity($array['title'], $array['template'], $array['listorder']);
        $template->id = $array['id'];
        return $template;
    }

    /**
     * Save template to databse, update when it already exists
     * @param TemplateEntity $template
     */
    public function save(TemplateEntity &$template)
    {
        if ($template->id != 0) {
            $this->update($template);
        } else {
            $this->db->query(
                sprintf(
                    'INSERT INTO %s
                    (title, template, listorder)
                    VALUES("%s", "%s",%d)',
                    $this->config->getTableName('template'),
                    $template->title,
                    addslashes($template->template),
                    $template->listorder
                )
            );
            $template->id = $this->db->insertedId();
        }
    }

    /**
     * Update the template in the database
     * @param TemplateEntity $template
     */
    public function update(TemplateEntity $template)
    {
        $query = sprintf(
            'UPDATE %s SET
                title = "%s",
                template = "%s",
                listorder = "%s"
             WHERE id = %d',
            $this->config->getTableName('template', true),
            $template->title,
            addslashes($template->template),
            $template->listorder,
            $template->id
        );

        $this->db->query($query);
    }

    /**
     * Add an image to this template
     * @param entities\TemplateEntity $template
     * @param $mime
     * @param $filename
     * @param $data
     * @param $width
     * @param $height
     */
    public function addImage(TemplateEntity $template, $mime, $filename, $data, $width, $height)
    {
        $image = new TemplateImageEntity($template->id, $mime, $filename, $data, $width, $height);
        $this->template_image->save($image);
    }

    /**
     * Get images used in this template
     * @param entities\TemplateEntity $template
     * @return array
     */
    public function getImages(TemplateEntity $template)
    {
        $images = array();
        $result = $this->db->query(
            sprintf(
                'SELECT * FROM %s
                WHERE template = %d',
                $this->config->getTableName('templateimage'),
                $template->id
            )
        )->fetchAll(\PDO::FETCH_ASSOC);
        foreach ($result as $img) {
            $imo = new TemplateImageEntity($img['template'], $img['mime'], $img['filename'], $img['data'], $img['width'], $img['height']);
            $imo->id = $img['id'];
            $images[] = $imo;
        }
        return $images;
    }
}