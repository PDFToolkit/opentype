<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Layout;

use PDFToolkit\OpenType\Binary\BinaryView;
use PDFToolkit\OpenType\Exception\MalformedFontException;
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
        if ($tag->value === 'size' && ($count !== 0 || $params === null)) {
            throw new MalformedFontException('The size feature requires parameters and cannot reference lookups.');
        }
        $parameters = $params === null ? null : (new FeatureParametersReader())->read($params, $tag, $budget);

        return new Feature($indices, $params, $parameters);
    }
}
