<?php

namespace Bundle\LichessBundle\Chess;

use Bundle\LichessBundle\Document\Player;
use Bundle\LichessBundle\Document\Game;
use Bundle\LichessBundle\Logger;
use LogicException;

class Drawer
{
    protected $messenger;
    protected $finisher;
    protected $logger;

    public function __construct(Messenger $messenger, Finisher $finisher, Logger $logger)
    {
        $this->messenger = $messenger;
        $this->finisher  = $finisher;
        $this->logger    = $logger;
    }

    /**
     * This players offers a draw
     *
     * @param Player $player
     * @return void
     */
    public function offer(Player $player)
    {
        $game = $player->getGame();
        if($game->getIsPlayable()) {
            if (!$game->getHasEnoughMovesToDraw()) {
                throw new LogicException('Too early to draw');
            }
            if(!$player->getIsOfferingDraw()) {
                if($player->getOpponent()->getIsOfferingDraw()) {
                    throw new DrawerConcurrentOfferException();
                }
                $this->messenger->addSystemMessage($game, 'Draw offer sent');
                $player->setIsOfferingDraw(true);
                $player->getOpponent()->addEventToStack(array('type' => 'reload_table'));
                $this->logger->notice($player, 'Player:offerDraw');
            } else {
                $this->logger->warn($player, 'Player:offerDraw already offered');
            }
        } else {
            $this->logger->warn($player, 'Player:offerDraw on finished game');
        }
    }

    /**
     * The player declines the opponent draw offer
     *
     * @param Player $player
     * @return void
     */
    public function decline(Player $player)
    {
        $game = $player->getGame();
        if($player->getOpponent()->getIsOfferingDraw()) {
            $this->messenger->addSystemMessage($game, 'Draw offer declined');
            $player->getOpponent()->setIsOfferingDraw(false);
            $player->getOpponent()->addEventToStack(array('type' => 'reload_table'));
            $this->logger->notice($player, 'Player:declineDrawOffer');
        } else {
            $this->logger->warn($player, 'Player:declineDrawOffer no offered draw');
        }
    }

    /**
     * The player accepts the opponent draw offer
     *
     * @param Player $player
     * @return void
     */
    public function accept(Player $player)
    {
        $game = $player->getGame();
        if($player->getOpponent()->getIsOfferingDraw()) {
            $this->messenger->addSystemMessage($game, 'Draw offer accepted');
            $game->setStatus(Game::DRAW);
            $this->finisher->finish($game);
            $game->addEventToStacks(array('type' => 'end'));
            $this->logger->notice($player, 'Player:acceptDrawOffer');
        } else {
            $this->logger->warn($player, 'Player:acceptDrawOffer no offered draw');
        }
    }

    /**
     * The player cancels his previous draw offer
     *
     * @param Player $player
     * @return void
     */
    public function cancel(Player $player)
    {
        $game = $player->getGame();
        if($player->getIsOfferingDraw()) {
            $this->messenger->addSystemMessage($game, 'Draw offer canceled');
            $player->setIsOfferingDraw(false);
            $player->getOpponent()->addEventToStack(array('type' => 'reload_table'));
            $this->logger->notice($player, 'Player:cancelDrawOffer');
        } else {
            $this->logger->warn($player, 'Player:cancelDrawOffer no offered draw');
        }
    }
}
