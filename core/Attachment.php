<?php
namespace phpList;

/**
 * Class Attachment
 * @package phpList
 */
class Attachment
{
    public $id = 0;
    public $filename;
    public $remotefile;
    public $mimetype;
    public $description;
    public $size;

    /**
     * @param string $filename
     * @param string $remotefile
     * @param string $mimetype
     * @param string $description
     * @param int $size
     */
    public function __construct($filename, $remotefile, $mimetype, $description, $size)
    {
        $this->filename = $filename;
        $this->remotefile = $remotefile;
        $this->mimetype = $mimetype;
        $this->description = $description;
        $this->size = $size;
    }

    /**
     * Save attachment to database
     */
    public function save()
    {
        if ($this->id != 0) {
            $this->update();
        } else {
            phpList::DB()->query(
                sprintf(
                    'INSERT INTO %s (filename,remotefile,mimetype,description,size)
                    VALUES("%s","%s","%s","%s",%d)',
                    Config::getTableName('attachment'),
                    $this->filename,
                    $this->remotefile,
                    $this->mimetype,
                    $this->description,
                    $this->size
                )
            );
            $this->id = phpList::DB()->insertedId();
        }
    }

    /**
     * Update attachment info in the database
     */
    public function update()
    {
        phpList::DB()->query(
            sprintf(
                'UPDATE %s SET
                filename = "%s",
                remotefile = "%s",
                mimetype = "%s",
                description = "%s",
                size = %d
                WHERE id = %d',
                Config::getTableName('attachment'),
                $this->filename,
                $this->remotefile,
                $this->mimetype,
                $this->description,
                $this->size,
                $this->id
            )
        );
    }

    /**
     * Get attachment with given id
     * @param $id
     * @return Attachment
     */
    public static function getAttachment($id)
    {
        $result = phpList::DB()->query(
            sprintf(
                'SELECT * FROM %s
                WHERE a.id = %d',
                Config::getTableName('attachment'),
                $id
            )
        );
        return Attachment::attachmentFromArray($result->fetch(\PDO::FETCH_ASSOC));
    }

    /**
     * Create an attachment object from db array
     * @param $array
     * @return Attachment
     */
    private static function attachmentFromArray($array)
    {
        $attachment = new Attachment($array['filename'], $array['remotefile'], $array['mimetype'], $array['description'], $array['size']);
        $attachment->id = $array['id'];
        return $attachment;
    }

    /**
     * Check if this attachment is used by any other campaigns and remove if not
     */
    public function removeIfOrphaned()
    {
        $result = phpList::DB()->query(
            sprintf(
                'SELECT messageid FROM %s
                WHERE attachmentid = %d',
                Config::getTableName('message_attachment'),
                $this->id
            )
        );
        if ($result->rowCount() == 0) {
            //remove from disk
            @unlink($this->remotefile);
        }
    }
}
