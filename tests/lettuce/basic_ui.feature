Feature: Nav Manager basic UI functionality
    In order to test nav manager basic UI features
    I'll reorder some pages

    # Disabled because WP is not correctly handling user switching.
    Scenario: User not permitted to edit order
        Given I am a contributor
        And I am logged in
        And I access bu-navigation site dashboard
        And I try to navigate to "Pages" > "Edit Order"
        Then navigation was unsuccessful

    Scenario: User permitted to edit order
        Given I am not logged in
        And I am a site_admin
        When I am logged in
        And I access bu-navigation site dashboard
        And I navigate to "Pages" > "Edit Order"
        And there are no asynch requests active
        Then the page contains the text "Page Order"

    Scenario: User is warned when leaving changes
        When I arrange "Page 1" after "Page 3"
        And I navigate to "Pages" > "Edit Order"
        Then an exit warning appears
        And I cancel the alert

    Scenario: User can reorder pages
        When I click the "Publish Changes" input button
        Then the page will eventually contain the text "Your navigation changes have been saved"

