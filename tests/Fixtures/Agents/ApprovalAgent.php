<?php

declare(strict_types=1);

namespace AndreAgroFerreira\AiSdkToolbox\Tests\Fixtures\Agents;

use Laravel\Ai\Concerns\RemembersConversations;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Promptable;

final class ApprovalAgent implements Agent, Conversational, HasTools
{
    use Promptable;
    use RemembersConversations;

    /**
     * @param  array<int, Tool>  $agentTools
     */
    public function __construct(
        private readonly array $agentTools = [],
    ) {}

    public function instructions(): string
    {
        return 'You run tools.';
    }

    /**
     * @return array<int, Tool>
     */
    public function tools(): iterable
    {
        return $this->agentTools;
    }
}
