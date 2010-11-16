<?php

namespace Bundle\LichessBundle\Document;

use Bundle\LichessBundle\Chess\Board;
use Bundle\LichessBundle\Util\KeyGenerator;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\ArrayCollection;
use Bundle\DoctrineUserBundle\Model\User;

/**
 * Represents a single Chess game
 *
 * @author     Thibault Duplessis <thibault.duplessis@gmail.com>
 *
 * @mongodb:Document(
 *   collection="game2",
 *   repositoryClass="Bundle\LichessBundle\Document\GameRepository"
 * )
 * @mongodb:HasLifecycleCallbacks
 */
class Game
{
    const CREATED = 10;
    const STARTED = 20;
    const MATE = 30;
    const RESIGN = 31;
    const STALEMATE = 32;
    const TIMEOUT = 33;
    const DRAW = 34;
    const OUTOFTIME = 35;

    const VARIANT_STANDARD = 1;
    const VARIANT_960 = 2;

    /**
     * Unique ID of the game
     *
     * @var string
     * @mongodb:Id(custom="true")
     */
    protected $id;

    /**
     * Game variant (like standard or 960)
     *
     * @var int
     * @mongodb:Field(type="int")
     */
    protected $variant;

    /**
     * The current state of the game, like CREATED, STARTED or MATE.
     *
     * @var int
     * @mongodb:Field(type="int")
     * @mongodb:Index()
     */
    protected $status;

    /**
     * The two players
     *
     * @var array
     * @mongodb:EmbedMany(targetDocument="Player")
     */
    protected $players;

    /**
     * User bound to the white player - optional
     *
     * @var User
     * @mongodb:ReferenceOne(targetDocument="Application\DoctrineUserBundle\Document\User")
     */
    protected $whiteUser = null;

    /**
     * User bound to the black player - optional
     *
     * @var User
     * @mongodb:ReferenceOne(targetDocument="Application\DoctrineUserBundle\Document\User")
     */
    protected $blackUser = null;

    /**
     * Color of the player who created the game
     *
     * @var string
     * @mongodb:Field(type="string")
     */
    protected $creatorColor;

    /**
     * Number of turns passed
     *
     * @var integer
     * @mongodb:Field(type="int")
     */
    protected $turns;

    /**
     * PGN moves of the game
     *
     * @var array
     * @mongodb:Field(type="collection")
     */
    protected $pgnMoves;

    /**
     * The ID of the player that starts the next game the players will play
     *
     * @var string
     * @mongodb:Field(type="string")
     */
    protected $next;

    /**
     * Fen notation of the initial position
     * Can be null if equals to standard position
     *
     * @var string
     * @mongodb:Field(type="string")
     */
    protected $initialFen;

    /**
     * Last update time
     *
     * @var \DateTime
     * @mongodb:Field(type="date")
     * @mongodb:Index(order="desc")
     */
    protected $updatedAt;

    /**
     * Creation date
     *
     * @var \DateTime
     * @mongodb:Field(type="date")
     */
    protected $createdAt;

    /**
     * Array of position hashes, used to detect threefold repetition
     *
     * @var array
     * @mongodb:Field(type="collection")
     */
    protected $positionHashes = array();

    /**
     * The game clock
     *
     * @var Clock
     * @mongodb:EmbedOne(targetDocument="Clock", nullable=true)
     */
    protected $clock;

    /**
     * The chat room
     *
     * @var Room
     * @mongodb:EmbedOne(targetDocument="Room", nullable=true)
     */
    protected $room;

    /**
     * The game board
     *
     * @var Board
     */
    protected $board;

    public function __construct($variant = self::VARIANT_STANDARD)
    {
        $this->generateId();
        $this->setVariant($variant);
        $this->status = self::CREATED;
        $this->turns = 0;
        $this->players = new ArrayCollection();
        $this->pgnMoves = array();
    }

    /**
     * @return User
     */
    public function getWhiteUser()
    {
        return $this->whiteUser;
    }

    /**
     * @param  User
     * @return null
     */
    public function setWhiteUser(User $user)
    {
        if($this->getIsStarted()) {
            throw new \LogicException('Can not assign user to a started game');
        }
        $this->whiteUser = $user;
    }

    /**
     * @return User
     */
    public function getBlackUser()
    {
        return $this->blackUser;
    }

    /**
     * @param  User
     * @return null
     */
    public function setBlackUser(User $user)
    {
        if($this->getIsStarted()) {
            throw new \LogicException('Can not assign user to a started game');
        }
        $this->blackUser = $user;
    }

    /**
     * Get the user bound to the player with this colo
     *
     * @return User
     **/
    public function getUser($color)
    {
        if('white' === $color) {
            return $this->getWhiteUser();
        }
        elseif('black' === $color) {
            return $this->getBlackUser();
        }

        throw new \InvalidArgumentException(sprintf('"%s" is not a regular chess color', $color));
    }

    /**
     * @return string
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Generate a new ID - don't use once the game is saved
     *
     * @return null
     **/
    protected function generateId()
    {
        if(null !== $this->id) {
            throw new \LogicException('Can not change the id of a saved game');
        }
        $this->id = KeyGenerator::generate(8);
    }

    /**
     * Fen notation of initial position
     *
     * @return string
     **/
    public function getInitialFen()
    {
        if(null === $this->initialFen) {
            return 'rnbqkbnr/pppppppp/8/8/8/8/PPPPPPPP/RNBQKBNR w KQkq';
        }

        return $this->initialFen;
    }

    /**
     * Set initialFen
     * @param  string
     * @return null
     */
    public function setInitialFen($fen)
    {
        $this->initialFen = $fen;
    }

    /**
     * Get variant
     * @return int
     */
    public function getVariant()
    {
        return $this->variant;
    }

    /**
     * Set variant
     * @param  int
     * @return null
     */
    public function setVariant($variant)
    {
        if(!array_key_exists($variant, self::getVariantNames())) {
            throw new \InvalidArgumentException(sprintf('%s is not a valid game variant', $variant));
        }
        if($this->getIsStarted()) {
            throw new \LogicException('Can not change variant, game is already started');
        }
        $this->variant = $variant;
    }

    public function isStandardVariant()
    {
        return static::VARIANT_STANDARD === $this->variant;
    }

    public function getVariantName()
    {
        $variants = self::getVariantNames();

        return $variants[$this->getVariant()];
    }

    static public function getVariantNames()
    {
        return array(
            self::VARIANT_STANDARD => 'standard',
            self::VARIANT_960 => 'chess960'
        );
    }

    /**
     * Get clock
     * @return Clock
     */
    public function getClock()
    {
        return $this->clock;
    }

    /**
     * Set clock
     * @param  Clock
     * @return null
     */
    public function setClock(Clock $clock)
    {
        if($this->getIsStarted()) {
            throw new \LogicException('Can not add clock, game is already started');
        }
        $this->clock = $clock;
    }

    public function setClockTime($time)
    {
        $this->setClock($time ? new Clock($time) : null);
    }

    /**
     * Tell if the game has a clock
     *
     * @return boolean
     **/
    public function hasClock()
    {
        return null !== $this->clock;
    }

    /**
     * Get the minutes of the clock if any, or 0
     *
     * @return int
     **/
    public function getClockMinutes()
    {
        return $this->hasClock() ? $this->getClock()->getLimitInMinutes() : 0;
    }

    public function getClockName()
    {
        return $this->hasClock() ? $this->getClock()->getName() : 'No clock';
    }

    /**
     * Verify if one of the player exceeded his time limit,
     * and terminate the game in this case
     *
     * @return boolean true if the game has been terminated
     **/
    public function checkOutOfTime()
    {
        if(!$this->hasClock()) {
            throw new \LogicException('This game has no clock');
        }
        if($this->getIsFinished()) {
            return;
        }
        foreach($this->getPlayers() as $player) {
            if($this->getClock()->isOutOfTime($player->getColor())) {
                $this->setStatus(static::OUTOFTIME);
                $player->getOpponent()->setIsWinner(true);
                return true;
            }
        }
    }

    /**
     * Add the current position hash to the stack
     */
    public function addPositionHash()
    {
        $hash = '';
        foreach($this->getPieces() as $piece) {
            $hash .= $piece->getContextualHash();
        }
        $this->positionHashes[] = md5($hash);
    }

    /**
     * Sometime we can safely clear the position hashes,
     * for example when a pawn moved
     *
     * @return void
     */
    public function clearPositionHashes()
    {
        $this->positionHashes = array();
    }

    /**
     * Are we in a threefold repetition state?
     *
     * @return bool
     **/
    public function isThreefoldRepetition()
    {
        $hash = end($this->positionHashes);

        return count(array_keys($this->positionHashes, $hash)) >= 3;
    }

    /**
     * Halfmove clock: This is the number of halfmoves since the last pawn advance or capture.
     * This is used to determine if a draw can be claimed under the fifty-move rule.
     *
     * @return int
     **/
    public function getHalfmoveClock()
    {
        return max(0, count($this->positionHashes) - 1);
    }

    /**
     * Fullmove number: The number of the full move. It starts at 1, and is incremented after Black's move.
     *
     * @return int
     **/
    public function getFullmoveNumber()
    {
        return floor(1+$this->getTurns() / 2);
    }

    /**
     * Return true if the game can not be won anymore
     * and can be declared as draw automatically
     *
     * @return boolean
     **/
    public function isCandidateToAutoDraw()
    {
        if(1 === $this->getPlayer('white')->getNbAlivePieces() && 1 === $this->getPlayer('black')->getNbAlivePieces()) {
            return true;
        }

        return false;
    }

    /**
     * Get pgn moves
     * @return array
     */
    public function getPgnMoves()
    {
        return $this->pgnMoves;
    }

    /**
     * Set pgn moves
     * @param  array
     * @return null
     */
    public function setPgnMoves(array $pgnMoves)
    {
        $this->pgnMoves = $pgnMoves;
    }

    /**
     * Add a pgn move
     *
     * @param string
     * @return null
     **/
    public function addPgnMove($pgnMove)
    {
        $this->pgnMoves[] = $pgnMove;
    }

    /**
     * Get next
     * @return string
     */
    public function getNext()
    {
        return $this->next;
    }

    /**
     * Set next
     * @param  string
     * @return null
     */
    public function setNext($next)
    {
        $this->next = $next;
    }

    /**
     * Get status
     * @return int
     */
    public function getStatus()
    {
        return $this->status;
    }

    public function getStatusMessage()
    {
        switch($this->getStatus()) {
        case self::MATE: $message      = 'Checkmate'; break;
        case self::RESIGN: $message    = ucfirst($this->getWinner()->getOpponent()->getColor()).' resigned'; break;
        case self::STALEMATE: $message = 'Stalemate'; break;
        case self::TIMEOUT: $message   = ucfirst($this->getWinner()->getOpponent()->getColor()).' left the game'; break;
        case self::DRAW: $message      = 'Draw'; break;
        case self::OUTOFTIME: $message = 'Time out'; break;
        default: $message              = '';
        }
        return $message;
    }

    /**
     * Set status
     * @param  int
     * @return null
     */
    public function setStatus($status)
    {
        if($this->getIsFinished()) {
            return;
        }

        $this->status = $status;

        if($this->getIsFinished() && $this->hasClock()) {
            $this->getClock()->stop();
        }
    }

    /**
     * Start a game
     *
     * @return null
     **/
    public function start()
    {
        $this->setStatus(static::STARTED);
        if(!$this->getInvited()->getIsAi()) {
            if(!$this->hasRoom()) {
                $this->setRoom(new Room());
            }
            $this->getRoom()->addMessage('system', ucfirst($this->getCreator()->getColor()).' creates the game');
            $this->getRoom()->addMessage('system', ucfirst($this->getInvited()->getColor()).' joins the game');
        }
    }

    /**
     * Get room
     * @return Room
     */
    public function getRoom()
    {
        return $this->room;
    }

    public function hasRoom()
    {
        return null !== $this->room;
    }

    /**
     * Set room
     * @param  Room
     * @return null
     */
    public function setRoom($room)
    {
        $this->room = $room;
    }

    /**
     * @return Board
     */
    public function getBoard()
    {
        if(null === $this->board) {
            $this->ensureDependencies();
        }

        return $this->board;
    }

    /**
     * @param Board
     */
    public function setBoard($board)
    {
        $this->board = $board;
    }

    /**
     * @return boolean
     */
    public function getIsFinished()
    {
        return $this->getStatus() >= self::MATE;
    }

    /**
     * @return boolean
     */
    public function getIsStarted()
    {
        return $this->getStatus() >= self::STARTED;
    }

    /**
     * @return boolean
     */
    public function getIsTimeOut()
    {
        return $this->getStatus() === self::TIMEOUT;
    }

    /**
     * @return Collection
     */
    public function getPlayers()
    {
        return $this->players;
    }

    /**
     * @return Player
     */
    public function getPlayer($color)
    {
        foreach($this->getPlayers() as $player) {
            if($color === $player->getColor()) {
                return $player;
            }
        }
    }

    /**
     * @return Player
     */
    public function getPlayerById($id)
    {
        if($this->getPlayer('white')->getId() === $id) {
            return $this->getPlayer('white');
        }
        elseif($this->getPlayer('black')->getId() === $id) {
            return $this->getPlayer('black');
        }
    }

    /**
     * @return Player
     */
    public function getTurnPlayer()
    {
        return $this->getPlayer($this->getTurnColor());
    }

    /**
     * Color who plays
     *
     * @return string
     **/
    public function getTurnColor()
    {
        return $this->turns%2 ? 'black' : 'white';
    }

    /**
     * @return string
     */
    public function getCreatorColor()
    {
        return $this->creatorColor;
    }

    /**
     * @param  string
     * @return null
     */
    public function setCreatorColor($creatorColor)
    {
        $this->creatorColor = $creatorColor;
    }

    /**
     * @return Player
     */
    public function getCreator()
    {
        return $this->getPlayer($this->getCreatorColor());
    }

    /**
     * @return Player
     */
    public function getInvited()
    {
        if($this->getCreator()->isWhite()) {
            return $this->getPlayer('black');
        } elseif($this->getCreator()->isBlack()) {
            return $this->getPlayer('white');
        }
    }

    public function setCreator(Player $player)
    {
        $this->setCreatorColor($player->getColor());
    }

    public function getWinner()
    {
        foreach($this->getPlayers() as $player) {
            if($player->getIsWinner()) {
                return $player;
            }
        }
    }

    public function addPlayer(Player $player)
    {
        $this->players->add($player);
        $player->setGame($this);
    }

    /**
     * @return integer
     */
    public function getTurns()
    {
        return $this->turns;
    }

    /**
     * @param integer
     */
    public function setTurns($turns)
    {
        $this->turns = $turns;
    }

    public function addTurn()
    {
        ++$this->turns;
    }

    public function getPieces()
    {
        return array_merge($this->getPlayer('white')->getPieces(), $this->getPlayer('black')->getPieces());
    }

    /**
     * Get updatedAt
     * @return \DateTime
     */
    public function getUpdatedAt()
    {
        return $this->updatedAt;
    }

    /**
     * Set updatedAt
     * @param  \DateTime
     * @return null
     */
    public function setUpdatedAt(\DateTime $updatedAt)
    {
        $this->updatedAt = $updatedAt;
    }

    /**
     * Get createdAt
     * @return \DateTime
     */
    public function getCreatedAt()
    {
        return $this->createdAt;
    }

    /**
     * Set createdAt
     * @param  \DateTime
     * @return null
     */
    public function setCreatedAt(\DateTime $createdAt)
    {
        $this->createdAt = $createdAt;
    }

    public function __toString()
    {
        return '#'.$this->getId(). 'turn '.$this->getTurns();
    }

    /**
     * @mongodb:PrePersist
     */
    public function setCreatedNow()
    {
        $this->createdAt = new \DateTime();
    }

    /**
     * @mongodb:PreUpdate
     */
    public function setUpdatedNow()
    {
        $this->updatedAt = new \DateTime();
    }

    /**
     * @mongodb:PostLoad
     */
    public function ensureDependencies()
    {
        $this->board = new Board($this);

        foreach($this->getPlayers() as $player) {
            $player->setGame($this);
            foreach($player->getPieces() as $piece) {
                $piece->setPlayer($player);
                $piece->setBoard($this->board);
            }
        }
    }

    /**
     * @mongodb:PreUpdate
     */
    public function rotatePlayerStacks()
    {
        foreach($this->getPlayers() as $player) {
            if(!$player->getIsAi()) {
                $player->getStack()->rotate();
            }
        }
    }

    /**
     * @mongodb:PreUpdate
     * @mongodb:PrePersist
     */
    public function cachePlayerVersions()
    {
        foreach($this->getPlayers() as $player) {
            if(!$player->getIsAi()) {
                apc_store($this->getId().'.'.$player->getColor().'.data', $player->getStack()->getVersion(), 3600);
            }
        }
    }

    /**
     * @mongodb:PostRemove
     */
    public function clearPlayerVersionCache()
    {
        foreach($this->getPlayers() as $player) {
            apc_delete($this->getId().'.'.$player->getColor().'.data');
        }
    }
}