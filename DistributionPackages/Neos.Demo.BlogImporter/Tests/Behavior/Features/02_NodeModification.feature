Feature: Consecutive import - Node Modification

  Background:
    And I initialize content repository "default"
    And I am in content repository "default"
    And the command CreateRootWorkspace is executed with payload:
      | Key                | Value           |
      | workspaceName      | "live"          |
      | newContentStreamId | "cs-identifier" |
    And I am in workspace "live" and dimension space point {"language": "en_US"}
    And the command CreateRootNodeAggregateWithNode is executed with payload:
      | Key             | Value             |
      | nodeAggregateId | "sites"           |
      | nodeTypeName    | "Neos.Neos:Sites" |

    And the following CreateNodeAggregateWithNode commands are executed:
      | nodeAggregateId    | parentNodeAggregateId | nodeTypeName                    | initialPropertyValues | originDimensionSpacePoint | nodeName |
      | demo-neos-homepage | sites                 | Neos.Demo:Document.Homepage     | {"title": "home"}     | {"language": "en_US"}     | site-a   |
      | demo-neos-blog     | demo-neos-homepage    | Neos.Demo:Document.Blog         | {"title": "blog"}     | {"language": "en_US"}     |          |
      | demo-neos-barcamp  | demo-neos-blog        | Neos.Demo:Document.BlogCategory | {"title": "Barcamp"}  | {"language": "en_US"}     |          |
      | demo-neos-neos-9   | demo-neos-blog        | Neos.Demo:Document.BlogCategory | {"title": "Neos 9"}   | {"language": "en_US"}     |          |

    And the command CreateNodeVariant is executed with payload:
      | Key             | Value                |
      | nodeAggregateId | "demo-neos-homepage" |
      | sourceOrigin    | {"language":"en_US"} |
      | targetOrigin    | {"language":"de"}    |

    And the command CreateNodeVariant is executed with payload:
      | Key             | Value                |
      | nodeAggregateId | "demo-neos-blog"     |
      | sourceOrigin    | {"language":"en_US"} |
      | targetOrigin    | {"language":"de"}    |

    And the command CreateNodeVariant is executed with payload:
      | Key             | Value                |
      | nodeAggregateId | "demo-neos-barcamp"  |
      | sourceOrigin    | {"language":"en_US"} |
      | targetOrigin    | {"language":"de"}    |

    And the command CreateNodeVariant is executed with payload:
      | Key             | Value                |
      | nodeAggregateId | "demo-neos-neos-9"   |
      | sourceOrigin    | {"language":"en_US"} |
      | targetOrigin    | {"language":"de"}    |

  Scenario: Check property update
    When I import file "sample1" into blog "demo-neos-blog"
    When I import file "sample2" into blog "demo-neos-blog"

    When I am in workspace "live" and dimension space point {"language": "en_US"}
    Then I expect node aggregate identifier "demo-neos-20241028" to lead to node cs-identifier;demo-neos-20241028;{"language": "en_US"}
    And I expect this node to have the following properties:
      | Key           | Value                                                                                                                                                                                                 |
      | title         | "Neos Barcamp 2024: A Recap"                                                                                                                                                                          |
      | abstract      | "On 25 October 2024, the first Neos Barcamp took place in Dresden - a day that brought the Neos community together and provided space for numerous exciting presentations and intensive discussions." |
      | datePublished | Date:2024-10-28T12:00:00+00:00                                                                                                                                                                        |
      | authorName    | "Marika Hauke"                                                                                                                                                                                        |
    And I expect this node to have the following references:
      | Name  | Node                                                |
      | categories | cs-identifier;demo-neos-barcamp;{"language":"en_US"} |

    When I am in workspace "live" and dimension space point {"language": "de"}
    Then I expect node aggregate identifier "demo-neos-20241028" to lead to node cs-identifier;demo-neos-20241028;{"language": "de"}
    And I expect this node to have the following properties:
      | Key           | Value                                                                                                                                                                                          |
      | title         | "Neos Barcamp 2024: Eine Zusammenfassung"                                                                                                                                                      |
      | abstract      | "Am 25. Oktober 2024 fand das erste Neos Barcamp in Dresden statt - ein Tag, der die Neos-Community zusammen brachte und Raum für zahlreiche Präsentationen und intensive Diskussionen schuf." |
      | datePublished | Date:2024-10-28T12:00:00+00:00                                                                                                                                                                 |
      | authorName    | "Marika Hauke"                                                                                                                                                                                 |
    And I expect this node to have the following references:
      | Name  | Node                                              |
      | categories | cs-identifier;demo-neos-barcamp;{"language":"de"} |
