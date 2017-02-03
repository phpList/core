<?php
namespace phpList;

use phpList\Entity\TemplateImageEntity;

/**
 * Class TemplateImage
 * @package phpList
 */
class TemplateImage
{
    protected $db;
    protected $config;

    public function __construct(Config $config, helper\Database $db)
    {
        $this->db = $db;
        $this->config = $config;
    }

    /**
     * Save image to database
     * @param entities\TemplateImageEntity $image
     */
    public function save(TemplateImageEntity &$image)
    {
        $this->db->query(
            sprintf(
                'INSERT INTO %s
                (template, mimetype, filename, data, width, height)
                VALUES(%d, "%s", "%s", "%s", %d, %d)',
                $this->config->getTableName('templateimage'),
                $image->template,
                $image->mimetype,
                $image->filename,
                $image->data,
                $image->width,
                $image->height
            )
        );
        $image->id = $this->db->insertedId();
    }

    /**
     * Remove this image from database
     * @param entities\TemplateImageEntity $image
     */
    public function delete(TemplateImageEntity $image)
    {
        $this->db->query(
            sprintf(
                'DELETE FROM %s
                WHERE id = %d)',
                $this->config->getTableName('templateimage'),
                $image->id
            )
        );
    }
}
