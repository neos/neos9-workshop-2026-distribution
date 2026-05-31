<?php

declare(strict_types=1);

use Behat\Gherkin\Node\TableNode;
use Neos\ContentRepository\Core\SharedModel\Node\NodeAggregateId;
use Neos\Demo\BlogImporter\BlogPostingWasPublished;
use Neos\Demo\BlogImporter\FakePublicationEventProvider;
use Neos\Demo\BlogImporter\Importer;
use Neos\Demo\BlogImporter\FsCsvPublicationEventProvider;

trait BlogImporterTrait
{
    /** @phpstan-ignore constant.notFound */
    const FIXTURE_PATH = __DIR__ . '/../Fixtures/';

    /**
     * @When /I import file "([^"]*)" into blog "([^"]*)"/
     */
    public function iImportFileIntoBlog(string $filename, string $blogId): void
    {
        $subject = new Importer(
            importEventProvider: new FsCsvPublicationEventProvider(self::FIXTURE_PATH . $filename . '.csv'),
            contentRepository: $this->currentContentRepository
        );
        $subject->run(NodeAggregateId::fromString($blogId));
    }

    /**
     * @When /I import the contents into blog "([^"]*)"/
     */
    public function iImportContentsIntoBlog(string $blogId, TableNode $contents): void
    {
        $subject = new Importer(
            importEventProvider: new FakePublicationEventProvider(
                array_map(
                    fn ($record) => new BlogPostingWasPublished(
                        $record['id'],
                        $record['language'] ?: null,
                        json_decode($record['headline']),
                        json_decode($record['abstract']),
                        \DateTimeImmutable::createFromFormat(\DateTimeImmutable::W3C, $record['datePublished']) ?: throw new \RuntimeException(sprintf('Date %s is not valid', $record['datePublished']), 1747205914),
                        json_decode($record['author']),
                        json_decode($record['about']),
                    ),
                    $contents->getHash(),
                )
            ),
            contentRepository: $this->currentContentRepository
        );

        $subject->run(NodeAggregateId::fromString($blogId));
    }

    /**
     * @template T of object
     * @param class-string<T> $className
     *
     * @return T
     */
    abstract private function getObject(string $className): object;
}
