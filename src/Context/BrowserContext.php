<?php
declare(strict_types=1);

namespace Behatch\Context;

use Behat\Behat\Tester\Exception\PendingException;
use Behat\Gherkin\Node\TableNode;
use Behat\Mink\Exception\ElementNotFoundException;
use Behat\Mink\Exception\ExpectationException;
use Behat\Mink\Exception\ResponseTextException;
use WebDriver\Exception\StaleElementReference;

class BrowserContext extends BaseContext
{
    private string $dateFormat = 'dmYHi';
    private int $timerStartedAt;

    public function __construct(
        private $timeout = 1
    ) {
    }

    /**
     * @AfterScenario
     */
    public function closeBrowser(): void
    {
        $this->getSession()->stop();
    }

    /**
     * @BeforeScenario
     *
     * @When (I )start timing now
     */
    public function startTimer(): void
    {
        $this->timerStartedAt = \time();
    }

    /**
     * Set login / password for next HTTP authentication
     *
     * @When I set basic authentication with :user and :password
     */
    public function iSetBasicAuthenticationWithAnd($user, $password): void
    {
        $this->getSession()->setBasicAuth($user, $password);
    }

    /**
     * Open url with various parameters
     *
     * @Given (I )am on url composed by:
     */
    public function iAmOnUrlComposedBy(TableNode $tableNode)
    {
        $url = '';
        foreach ($tableNode->getHash() as $hash) {
            $url .= $hash['parameters'];
        }

        return $this->getMinkContext()
            ->visit($url);
    }

    /**
     * Clicks on the nth CSS element
     *
     * @When (I )click on the :index :element element
     * @throws \Exception
     */
    public function iClickOnTheNthElement($index, $element): void
    {
        $node = $this->findElement('css', $element, $index);
        $node->click();
    }

    /**
     * Click on the nth specified link
     *
     * @When (I )follow the :index :link link
     * @throws \Exception
     */
    public function iFollowTheNthLink($index, $link): void
    {
        $node = $this->findElement('named', ['link', $link], $index);
        $node->click();
    }

    /**
     * Presses the nth specified button
     *
     * @When (I )press the :index :button button
     * @throws \Exception
     */
    public function pressTheNthButton($index, $button): void
    {
        $node = $this->findElement('named', ['button', $button], $index);
        $node->click();
    }

    /**
     * Fills in form field with current date
     *
     * @When (I )fill in :field with the current date
     */
    public function iFillInWithTheCurrentDate($field)
    {
        return $this->iFillInWithTheCurrentDateAndModifier($field, 'now');
    }

    /**
     * Fills in form field with current date and strtotime modifier
     *
     * @When (I )fill in :field with the current date and modifier :modifier
     */
    public function iFillInWithTheCurrentDateAndModifier($field, $modifier)
    {
        return $this->getMinkContext()
            ->fillField($field, \date($this->dateFormat, \strtotime($modifier)));
    }

    /**
     * Mouse over a CSS element
     *
     * @When (I )hover :element
     * @throws \Exception
     */
    public function iHoverIShouldSeeIn($element): void
    {
        $node = $this->getSession()->getPage()->find('css', $element);
        if ($node === null) {
            throw new \Exception("The hovered element '$element' was not found anywhere in the page");
        }
        $node->mouseOver();
    }

    /**
     * Save value of the field in parameters array
     *
     * @When (I )save the value of :field in the :parameter parameter
     * @throws \Exception
     */
    public function iSaveTheValueOfInTheParameter($field, $parameter): void
    {
        $field = \str_replace('\\"', '"', $field);
        $node = $this->getSession()->getPage()->findField($field);
        if ($node === null) {
            throw new \Exception("The field '$field' was not found anywhere in the page");
        }

        $this->setMinkParameter($parameter, $node->getValue());
    }

    /**
     * Checks, that the page should contains specified text after given timeout
     *
     * @Then (I )wait :count second(s) until I see :text
     * @throws ResponseTextException
     * @throws ExpectationException
     */
    public function iWaitSecondsUntilISee($count, $text): void
    {
        $this->iWaitSecondsUntilISeeInTheElement($count, $text, 'html');
    }

    /**
     * Checks, that the page should not contain specified text before given timeout
     *
     * @Then (I )should not see :text within :count second(s)
     * @throws ExpectationException
     */
    public function iDontSeeInSeconds($count, $text): void
    {
        $caught = false;
        try {
            $this->iWaitSecondsUntilISee($count, $text);
        } catch (ExpectationException) {
            $caught = true;
        }

        $this->assertTrue($caught, "Text '$text' has been found");
    }

    /**
     * Checks, that the page should contains specified text after timeout
     *
     * @Then (I )wait until I see :text
     * @throws ResponseTextException|ExpectationException
     */
    public function iWaitUntilISee($text): void
    {
        $this->iWaitSecondsUntilISee($this->timeout, $text);
    }

    /**
     * Checks, that the element contains specified text after timeout
     *
     * @Then (I )wait :count second(s) until I see :text in the :element element
     * @throws ResponseTextException|ExpectationException
     */
    public function iWaitSecondsUntilISeeInTheElement($count, $text, $element): void
    {
        $startTime = time();
        $this->iWaitSecondsForElement($count, $element);

        $expected = str_replace('\\"', '"', $text);
        $message = "The text '$expected' was not found after a $count seconds timeout";

        $found = false;
        do {
            try {
                \usleep(1000);
                $node = $this->getSession()->getPage()->find('css', $element);
                $this->assertContains($expected, $node->getText(), $message);

                return;
            } catch (ExpectationException) {
                /* Intentionally leave blank */
            } catch (StaleElementReference) {
                // assume page reloaded whilst we were still waiting
            }
        } while (!$found && (\time() - $startTime < $count));

        // final assertion...
        $node = $this->getSession()->getPage()->find('css', $element);
        $this->assertContains($expected, $node->getText(), $message);
    }

    /**
     * @Then (I )wait :count second(s)
     */
    public function iWaitSeconds(float|int|string $count): void
    {
        \usleep((int) \round((float) $count * 1_000_000));
    }

    /**
     * Checks, that the element contains specified text after timeout
     *
     * @Then (I )wait until I see :text in the :element element
     * @throws ResponseTextException|ExpectationException
     */
    public function iWaitUntilISeeInTheElement($text, $element): void
    {
        $this->iWaitSecondsUntilISeeInTheElement($this->timeout, $text, $element);
    }

    /**
     * Checks, that the page should contains specified element after timeout
     *
     * @Then (I )wait for :element element
     * @throws ResponseTextException
     */
    public function iWaitForElement($element): void
    {
        $this->iWaitSecondsForElement($this->timeout, $element);
    }

    /**
     * Wait for a element
     *
     * @Then (I )wait :count second(s) for :element element
     * @throws ResponseTextException
     */
    public function iWaitSecondsForElement($count, $element): void
    {
        $found = false;
        $startTime = \time();
        $e = null;

        do {
            try {
                \usleep(1000);
                $node = $this->getSession()->getPage()->findAll('css', $element);
                $this->assertCount(1, $node);
                $found = true;
            } catch (ExpectationException $e) {
                /* Intentionally leave blank */
            }
        } while (!$found && (\time() - $startTime < $count));

        if ($found === false) {
            $message = "The element '$element' was not found after a $count seconds timeout";
            throw new ResponseTextException($message, $this->getSession()->getDriver(), $e);
        }
    }

    /**
     * @Then /^(?:|I )should see (?P<count>\d+) "(?P<element>[^"]*)" in the (?P<index>\d+)(?:st|nd|rd|th) "(?P<parent>[^"]*)"$/
     * @throws \Exception
     */
    public function iShouldSeeNElementInTheNthParent($count, $element, $index, $parent): void
    {
        $actual = $this->countElements($element, $index, $parent);
        if ($actual !== $count) {
            throw new \Exception("$actual occurrences of the '$element' element in '$parent' found");
        }
    }

    /**
     * @Then (I )should see less than :count :element in the :index :parent
     * @throws \Exception
     */
    public function iShouldSeeLessThanNElementInTheNthParent($count, $element, $index, $parent): void
    {
        $actual = $this->countElements($element, $index, $parent);
        if ($actual > $count) {
            throw new \Exception("$actual occurrences of the '$element' element in '$parent' found");
        }
    }

    /**
     * @Then (I )should see more than :count :element in the :index :parent
     * @throws \Exception
     */
    public function iShouldSeeMoreThanNElementInTheNthParent($count, $element, $index, $parent): void
    {
        $actual = $this->countElements($element, $index, $parent);
        if ($actual < $count) {
            throw new \Exception("$actual occurrences of the '$element' element in '$parent' found");
        }
    }

    /**
     * Checks, that element with given CSS is enabled
     *
     * @Then the element :element should be enabled
     * @throws \Exception
     */
    public function theElementShouldBeEnabled($element): void
    {
        $node = $this->getSession()->getPage()->find('css', $element);
        if ($node === null) {
            throw new \Exception("There is no '$element' element");
        }

        if ($node->hasAttribute('disabled')) {
            throw new \Exception("The element '$element' is not enabled");
        }
    }

    /**
     * Checks, that element with given CSS is disabled
     *
     * @Then the element :element should be disabled
     * @throws ExpectationException
     */
    public function theElementShouldBeDisabled($element): void
    {
        $this->not(
            function () use ($element) {
                $this->theElementShouldBeEnabled($element);
            },
            "The element '$element' is not disabled"
        );
    }

    /**
     * Checks, that given select box contains the specified option
     *
     * @Then the :select select box should contain :option
     * @throws ElementNotFoundException|ExpectationException
     */
    public function theSelectBoxShouldContain($select, $option): void
    {
        $select = str_replace('\\"', '"', $select);
        $option = str_replace('\\"', '"', $option);

        $obj = $this->getSession()->getPage()->findField($select);
        if ($obj === null) {
            throw new ElementNotFoundException(
                $this->getSession()->getDriver(), 'select box', 'id|name|label|value', $select
            );
        }
        $optionText = $obj->getText();

        $message = "The '$select' select box does not contain the '$option' option";
        $this->assertContains($option, $optionText, $message);
    }

    /**
     * Checks, that given select box does not contain the specified option
     *
     * @Then the :select select box should not contain :option
     * @throws ExpectationException
     */
    public function theSelectBoxShouldNotContain($select, $option): void
    {
        $this->not(
            function () use ($select, $option) {
                $this->theSelectBoxShouldContain($select, $option);
            },
            "The '$select' select box does contain the '$option' option"
        );
    }

    /**
     * Checks, that the specified CSS element is visible
     *
     * @Then the :element element should be visible
     * @throws \Exception
     */
    public function theElementShouldBeVisible($element): void
    {
        $displayedNode = $this->getSession()->getPage()->find('css', $element);
        if ($displayedNode === null) {
            throw new \Exception("The element '$element' was not found anywhere in the page");
        }

        $message = "The element '$element' is not visible";
        $this->assertTrue($displayedNode->isVisible(), $message);
    }

    /**
     * Checks, that the specified CSS element is not visible
     *
     * @Then the :element element should not be visible
     * @throws ExpectationException
     */
    public function theElementShouldNotBeVisible($element): void
    {
        $exception = new \Exception("The element '$element' is visible");

        $this->not(
            function () use ($element) {
                $this->theElementShouldBeVisible($element);
            },
            $exception
        );
    }

    /**
     * Select a frame by its name or ID.
     *
     * @When (I )switch to iframe :name
     * @When (I )switch to frame :name
     */
    public function switchToIFrame($name): void
    {
        $this->getSession()->switchToIFrame($name);
    }

    /**
     * Go back to main document frame.
     *
     * @When (I )switch to main frame
     */
    public function switchToMainFrame(): void
    {
        $this->getSession()->switchToIFrame();
    }

    /**
     * test time from when the scenario started
     *
     * @Then (the )total elapsed time should be :comparison than :expected seconds
     * @Then (the )total elapsed time should be :comparison to :expected seconds
     * @throws ExpectationException
     */
    public function elapsedTime($comparison, $expected): void
    {
        $elapsed = \time() - $this->timerStartedAt;

        switch ($comparison) {
            case 'less':
                $this->assertTrue(
                    $elapsed < $expected,
                    "Elapsed time '$elapsed' is not less than '$expected' seconds."
                );
                break;

            case 'more':
                $this->assertTrue(
                    $elapsed > $expected,
                    "Elapsed time '$elapsed' is not more than '$expected' seconds."
                );
                break;

            case 'equal':
                $this->assertSame($elapsed, $expected, "Elapsed time '$elapsed' is not '$expected' seconds.");
                break;

            default:
                throw new PendingException("Unknown comparison '$comparison'. Use 'less', 'more' or 'equal'");
        }
    }
}
