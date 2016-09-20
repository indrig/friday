<?php
namespace Friday\Base;

interface DestroyableInterface{
    /**
     * Очищает все связи в нутри объекта
     *
     * @return void
     */
    public function destroy();
}