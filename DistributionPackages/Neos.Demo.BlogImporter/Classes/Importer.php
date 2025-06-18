<?php

declare(strict_types=1);

namespace Neos\Demo\BlogImporter;

use Neos\ContentRepository\Core\ContentRepository;
use Neos\ContentRepository\Core\DimensionSpace\OriginDimensionSpacePoint;
use Neos\ContentRepository\Core\Feature\NodeCreation\Command\CreateNodeAggregateWithNode;
use Neos\ContentRepository\Core\Feature\NodeModification\Command\SetNodeProperties;
use Neos\ContentRepository\Core\Feature\NodeModification\Dto\PropertyValuesToWrite;
use Neos\ContentRepository\Core\Feature\NodeReferencing\Command\SetNodeReferences;
use Neos\ContentRepository\Core\Feature\NodeReferencing\Dto\NodeReferencesForName;
use Neos\ContentRepository\Core\Feature\NodeReferencing\Dto\NodeReferencesToWrite;
use Neos\ContentRepository\Core\Feature\NodeRemoval\Command\RemoveNodeAggregate;
use Neos\ContentRepository\Core\Feature\NodeVariation\Command\CreateNodeVariant;
use Neos\ContentRepository\Core\NodeType\NodeTypeName;
use Neos\ContentRepository\Core\Projection\ContentGraph\Filter\FindChildNodesFilter;
use Neos\ContentRepository\Core\Projection\ContentGraph\Filter\Pagination\Pagination;
use Neos\ContentRepository\Core\Projection\ContentGraph\Filter\PropertyValue\Criteria\PropertyValueEquals;
use Neos\ContentRepository\Core\Projection\ContentGraph\VisibilityConstraints;
use Neos\ContentRepository\Core\SharedModel\Node\NodeAggregateId;

use Neos\ContentRepository\Core\SharedModel\Node\NodeAggregateIds;
use Neos\ContentRepository\Core\SharedModel\Node\NodeVariantSelectionStrategy;
use Neos\ContentRepository\Core\SharedModel\Node\PropertyName;
use Neos\ContentRepository\Core\SharedModel\Node\ReferenceName;
use Neos\ContentRepository\Core\SharedModel\Workspace\WorkspaceName;
use Neos\Flow\Annotations as Flow;

#[Flow\Proxy(false)]
final class Importer
{
    public function __construct(
        private readonly PublicationEventProviderInterface $importEventProvider,
        private readonly ContentRepository                 $contentRepository,
    )
    {
    }

    public function run(NodeAggregateId $blogId): void
    {
        echo $this->contentRepository->id;

        $workspaceName = WorkspaceName::forLive();
        $contentGraph = $this->contentRepository->getContentGraph($workspaceName);

        // Collect existing blog posts
        $childNodeAggregates = $contentGraph->findChildNodeAggregates(
            parentNodeAggregateId: $blogId,
        );
        $existingBlogPosts = [];
        foreach ($childNodeAggregates as $childNodeAggregate) {
            if ($childNodeAggregate->nodeTypeName->value !== 'Neos.Demo:Document.BlogPosting') {
                continue;
            }
            foreach ($childNodeAggregate->occupiedDimensionSpacePoints as $dimensionSpacePoint) {
                $existingBlogPosts[$childNodeAggregate->nodeAggregateId->value][$dimensionSpacePoint->hash] = $dimensionSpacePoint;
            }
        }

        $blogCategoriesByDimensionSpacePoint = [];

        foreach ($this->importEventProvider->read() as $event) {
            $language = $event->language;
            if ($language === 'en') {
                $language = 'en_US';
            }

            $originDimensionSpacePoint = OriginDimensionSpacePoint::fromArray([
                'language' => $language
            ]);

            $subgraph = $this->contentRepository->getContentGraph($workspaceName)->getSubgraph(
                $originDimensionSpacePoint->toDimensionSpacePoint(),
                VisibilityConstraints::createEmpty(),
            );

            $nodeAggregateId = NodeAggregateId::fromString('demo-neos-' . $event->id);

            $nodeAggregate = $contentGraph->findNodeAggregateById($nodeAggregateId);

            $categoryTitle = $event->about;
            if (!isset($blogCategoriesByDimensionSpacePoint[$originDimensionSpacePoint->hash][$categoryTitle])) {
                $categoryNode = $subgraph->findChildNodes(
                    parentNodeAggregateId: $blogId,
                    filter: FindChildNodesFilter::create(
                        nodeTypes: 'Neos.Demo:Document.BlogCategory',
                        propertyValue: PropertyValueEquals::create(
                            propertyName: PropertyName::fromString('title'),
                            value: $categoryTitle,
                            caseSensitive: true,
                        ),
                        pagination: Pagination::fromLimitAndOffset(1, 0),
                    )
                )->first();

                if ($categoryNode === null) {
                    throw new \Exception(sprintf('Missing category %s', $categoryTitle));
                }

                // Note: there is no transactionality outside of commands

                $blogCategoriesByDimensionSpacePoint[$originDimensionSpacePoint->hash][$categoryTitle] = $categoryNode->aggregateId;
            }
            $categoryNodeAggregateId = $blogCategoriesByDimensionSpacePoint[$originDimensionSpacePoint->hash][$categoryTitle];

            $referencesToWrite = NodeReferencesToWrite::create(
                NodeReferencesForName::fromTargets(
                    ReferenceName::fromString('categories'),
                    NodeAggregateIds::fromArray([$categoryNodeAggregateId]),
                ),
            );

            $propertyValuesToWrite = PropertyValuesToWrite::fromArray([
                'title' => $event->headline,
                'abstract' => $event->abstract,
                'datePublished' => $event->datePublished,
                'authorName' => $event->author,
            ]);

            if ($nodeAggregate === null) {
                $this->contentRepository->handle(CreateNodeAggregateWithNode::create(
                    workspaceName: $workspaceName,
                    nodeAggregateId: $nodeAggregateId,
                    nodeTypeName: NodeTypeName::fromString('Neos.Demo:Document.BlogPosting'),
                    originDimensionSpacePoint: $originDimensionSpacePoint,
                    parentNodeAggregateId: $blogId,
                    initialPropertyValues: $propertyValuesToWrite,
                    references: $referencesToWrite,
                ));
            } else {
                if (!$nodeAggregate->occupiesDimensionSpacePoint($originDimensionSpacePoint)) {
                    foreach ($nodeAggregate->occupiedDimensionSpacePoints->getPoints() as $point) {
                        $sourceOrigin = $point;
                        break;
                    }

                    $this->contentRepository->handle(CreateNodeVariant::create(
                        workspaceName: $workspaceName,
                        nodeAggregateId: $nodeAggregateId,
                        sourceOrigin: $sourceOrigin,
                        targetOrigin: $originDimensionSpacePoint,
                    ));
                }

                $this->contentRepository->handle(SetNodeProperties::create(
                    workspaceName: $workspaceName,
                    nodeAggregateId: $nodeAggregateId,
                    originDimensionSpacePoint: $originDimensionSpacePoint,
                    propertyValues: $propertyValuesToWrite,
                ));

                $this->contentRepository->handle(SetNodeReferences::create(
                    workspaceName: $workspaceName,
                    sourceNodeAggregateId: $nodeAggregateId,
                    sourceOriginDimensionSpacePoint: $originDimensionSpacePoint,
                    references: $referencesToWrite,
                ));
            }

            if (isset($existingBlogPosts[$nodeAggregateId->value])) {
                if (isset($existingBlogPosts[$nodeAggregateId->value][$originDimensionSpacePoint->hash])) {
                    unset($existingBlogPosts[$nodeAggregateId->value][$originDimensionSpacePoint->hash]);
                }
            }
        }

        foreach ($existingBlogPosts as $nodeAggregateId => $dimensionSpacePoints) {
            foreach ($dimensionSpacePoints as $dimensionSpacePoint) {
                $this->contentRepository->handle(RemoveNodeAggregate::create(
                    workspaceName: $workspaceName,
                    nodeAggregateId: NodeAggregateId::fromString($nodeAggregateId),
                    coveredDimensionSpacePoint: $dimensionSpacePoint->toDimensionSpacePoint(),
                    nodeVariantSelectionStrategy: NodeVariantSelectionStrategy::STRATEGY_ALL_SPECIALIZATIONS,
                ));
            }
        }
    }

}
