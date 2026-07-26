<?php

declare(strict_types=1);

namespace AndreAgroFerreira\AiSdkToolbox\Console;

use AndreAgroFerreira\AiSdkToolbox\Knowledge\KnowledgeSyncer;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Description('Synchronize knowledge sources into the vector store')]
#[Signature('ai:kb-sync
    {source? : The source name from the knowledge.sources configuration}
    {--sync : Run synchronously instead of dispatching queue jobs}')]
final class KnowledgeSyncCommand extends Command
{
    public function handle(KnowledgeSyncer $syncer): int
    {
        $name = $this->argument('source');
        $inline = (bool) $this->option('sync');

        if (is_string($name)) {
            $source = $syncer->source($name);

            if ($source === null) {
                $this->components->error(sprintf('Unknown knowledge source [%s].', $name));

                return self::FAILURE;
            }

            $sources = [$source];
        } else {
            $sources = $syncer->sources();

            if ($sources === []) {
                $this->components->warn('No knowledge sources configured (knowledge.sources).');

                return self::SUCCESS;
            }
        }

        foreach ($sources as $source) {
            $report = $syncer->sync($source, $inline);

            $this->components->info(sprintf(
                '[%s] %d %s, %d unchanged, %d deleted%s.',
                $report->source,
                $report->synced,
                $inline ? 'synced' : 'queued',
                $report->skipped,
                $report->deleted,
                $report->failed === [] ? '' : sprintf(', %d failed', count($report->failed)),
            ));

            if ($report->failed !== []) {
                $this->components->bulletList($report->failed);
            }
        }

        return self::SUCCESS;
    }
}
