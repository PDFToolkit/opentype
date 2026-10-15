<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Layout;

use PDFToolkit\OpenType\Binary\BinaryView;
use PDFToolkit\OpenType\Exception\MalformedFontException;
use PDFToolkit\OpenType\Exception\UnsupportedFontException;
use PDFToolkit\OpenType\Substitution\LayoutReadBudget;
use PDFToolkit\OpenType\Value\Tag;

/** Shared typed ScriptList and FeatureList decoding. */
final readonly class LayoutListsReader
{
    private function child(BinaryView $parent, int $offset, int $minimum): BinaryView
    {
        if ($offset < $minimum || $offset >= $parent->size()) {
            throw new MalformedFontException('A required GSUB offset is null, inside its header or beyond its table.');
        }

        return $parent->view($offset);
    }

    private function records(BinaryView $table, int $offset, int $count, int $width, LayoutReadBudget $budget): void
    {
        $budget->consume($count);
        $table->bytes($offset, $count * $width);
    }

    private function tag(BinaryView $table, int $offset): Tag
    {
        try {
            return new Tag($table->bytes($offset, 4));
        } catch (\InvalidArgumentException $exception) {
            throw new MalformedFontException('Invalid OpenType layout tag.', 0, $exception);
        }
    }

    public function scripts(BinaryView $table, LayoutReadBudget $budget): ScriptList
    {
        $count = $table->uint16(0);
        $this->records($table, 2, $count, 6, $budget);
        $result = [];
        for ($i = 0; $i < $count; ++$i) {
            $offset = 2 + 6 * $i;
            $script = $this->child($table, $table->uint16($offset + 4), 2 + 6 * $count);
            $langCount = $script->uint16(2);
            $this->records($script, 4, $langCount, 6, $budget);
            $default = $script->uint16(0) === 0 ? null : $this->language($this->child($script, $script->uint16(0), 4 + 6 * $langCount), $budget);
            $languages = [];
            for ($j = 0; $j < $langCount; ++$j) {
                $at = 4 + 6 * $j;
                $languages[] = new LangSysRecord($this->tag($script, $at), $this->language($this->child($script, $script->uint16($at + 4), 4 + 6 * $langCount), $budget));
            }
            $result[] = new ScriptRecord($this->tag($table, $offset), new Script($default, $languages));
        }

        return new ScriptList($result);
    }

    private function language(BinaryView $table, LayoutReadBudget $budget): LangSys
    {
        if ($table->uint16(0) !== 0) {
            throw new MalformedFontException('LangSys.lookupOrderOffset is reserved and must be null.');
        }
        $required = $table->uint16(2);
        $count = $table->uint16(4);
        $this->records($table, 6, $count, 2, $budget);
        $indices = [];
        for ($i = 0; $i < $count; ++$i) {
            $indices[] = $table->uint16(6 + 2 * $i);
        }

        return new LangSys($required === 0xFFFF ? null : $required, $indices);
    }

    public function features(BinaryView $table, LayoutReadBudget $budget): FeatureList
    {
        $count = $table->uint16(0);
        $this->records($table, 2, $count, 6, $budget);
        $result = [];
        for ($i = 0; $i < $count; ++$i) {
            $offset = 2 + 6 * $i;
            $tag = $this->tag($table, $offset);
            $feature = $this->child($table, $table->uint16($offset + 4), 2 + 6 * $count);
            $result[] = new FeatureRecord($tag, $this->feature($feature, $tag, $budget));
        }

        return new FeatureList($result);
    }

    public function feature(BinaryView $table, Tag $tag, LayoutReadBudget $budget): Feature
    {
        $count = $table->uint16(2);
        $this->records($table, 4, $count, 2, $budget);
        $indices = [];
        for ($i = 0; $i < $count; ++$i) {
            $indices[] = $table->uint16(4 + 2 * $i);
        }
        $params = $table->uint16(0) === 0 ? null : $this->child($table, $table->uint16(0), 4 + 2 * $count);
        if ($params !== null) {
            $this->featureParams($params, $tag, $budget);
        }

        return new Feature($indices, $params);
    }

    private function featureParams(BinaryView $table, Tag $tag, LayoutReadBudget $budget): void
    {
        if ($tag->value === 'size') {
            $table->bytes(0, 10);

            return;
        }
        if (preg_match('/^ss(?:0[1-9]|1[0-9]|20)$/D', $tag->value) === 1) {
            $table->bytes(0, 4);
            if ($table->uint16(0) !== 0) {
                throw new UnsupportedFontException('Unsupported stylistic-set feature parameters version.');
            }

            return;
        }
        if (preg_match('/^cv(?:0[1-9]|[1-9][0-9])$/D', $tag->value) !== 1) {
            throw new MalformedFontException('This feature tag does not define feature parameters.');
        }
        if ($table->uint16(0) !== 0) {
            throw new UnsupportedFontException('Unsupported character-variant feature parameters version.');
        }
        $count = $table->uint16(12);
        $this->records($table, 14, $count, 3, $budget);
        for ($i = 0; $i < $count; ++$i) {
            $code = $table->uint24(14 + 3 * $i);
            if ($code > 0x10FFFF || ($code >= 0xD800 && $code <= 0xDFFF)) {
                throw new MalformedFontException('Feature parameters contain an invalid Unicode scalar.');
            }
        }
    }

}
