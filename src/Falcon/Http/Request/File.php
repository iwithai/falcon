<?php

namespace Falcon\Http\Request;

/**
 * Класс, представляющий один загруженный файл.
 */
class File
{

    protected string $name;
    protected string $type;
    protected string $tmpName;
    protected int $error;
    protected int $size;
    protected string $extension;

    public function __construct(array $fileData)
    {
        $this->name = $fileData['name'] ?? '';
        $this->type = $fileData['type'] ?? '';
        $this->tmpName = $fileData['tmp_name'] ?? '';
        $this->error = $fileData['error'] ?? UPLOAD_ERR_NO_FILE;
        $this->size = $fileData['size'] ?? 0;
        $this->extension = pathinfo($this->name, PATHINFO_EXTENSION);
    }

    /**
     * Возвращает исходное имя файла.
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Возвращает MIME-тип файла.
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * Возвращает временный путь к загруженному файлу.
     */
    public function getTempName(): string
    {
        return $this->tmpName;
    }

    /**
     * Возвращает код ошибки загрузки файла.
     * См. константы UPLOAD_ERR_*.
     */
    public function getError(): int
    {
        return $this->error;
    }

    /**
     * Возвращает размер файла в байтах.
     */
    public function getSize(): int
    {
        return $this->size;
    }

    /**
     * Возвращает расширение файла.
     */
    public function getExtension(): string
    {
        return $this->extension;
    }

    /**
     * Проверяет, был ли файл успешно загружен.
     */
    public function isUploadedFile(): bool
    {
        return $this->error === UPLOAD_ERR_OK && is_uploaded_file($this->tmpName);
    }

    /**
     * Перемещает загруженный файл в указанное место назначения.
     *
     * @param string $destination Путь, куда переместить файл.
     * @return bool True в случае успеха, false в случае ошибки.
     */
    public function moveTo(string $destination): bool
    {
        if (!$this->isUploadedFile()) {
            return false;
        }
        return move_uploaded_file($this->tmpName, $destination);
    }
}
