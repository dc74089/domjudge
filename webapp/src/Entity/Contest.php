<?php declare(strict_types=1);

namespace App\Entity;

use App\Controller\API\AbstractRestController;
use App\Doctrine\Constants;
use App\Utils\FreezeData;
use App\Utils\Utils;
use App\Validator\Constraints\Identifier;
use App\Validator\Constraints\TimeString;
use DateTime;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Exception;
use JMS\Serializer\Annotation as Serializer;
use OpenApi\Attributes as OA;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * Contests that will be run with this install.
 *
 * @UniqueEntity("shortname")
 * @UniqueEntity("externalid")
 */
#[ORM\Table(
    name: 'contest',
    options: [
        'collation' => 'utf8mb4_unicode_ci',
        'charset' => 'utf8mb4',
        'comment' => 'Contests that will be run with this install',
    ]
)]
#[ORM\Index(columns: ['cid', 'enabled'], name: 'cid')]
#[ORM\UniqueConstraint(name: 'externalid', columns: ['externalid'], options: ['lengths' => [190]])]
#[ORM\UniqueConstraint(name: 'shortname', columns: ['shortname'], options: ['lengths' => [190]])]
#[ORM\HasLifecycleCallbacks]
#[Serializer\VirtualProperty(
    name: 'formalName',
    exp: 'object.getName()',
    options: [new Serializer\Type('string')]
)]
#[Serializer\VirtualProperty(
    name: 'penaltyTime',
    exp: '0',
    options: [new Serializer\Type('int')]
)]
#[ORM\Entity]
class Contest extends BaseApiEntity implements AssetEntityInterface
{
    final public const STARTTIME_UPDATE_MIN_SECONDS_BEFORE = 30;

    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    #[ORM\Column(
        name: 'cid',
        type: 'integer',
        length: 4,
        nullable: false,
        options: ['comment' => 'Contest ID', 'unsigned' => true]
    )]
    #[Serializer\SerializedName('id')]
    #[Serializer\Type('string')]
    protected ?int $cid = null;

    #[ORM\Column(
        name: 'externalid',
        type: 'string',
        length: Constants::LENGTH_LIMIT_TINYTEXT,
        nullable: true,
        options: ['comment' => 'Contest ID in an external system', 'collation' => 'utf8mb4_bin']
    )]
    #[Serializer\Groups([AbstractRestController::GROUP_NONSTRICT])]
    #[Serializer\SerializedName('external_id')]
    protected ?string $externalid = null;

    /**
     * @Assert\NotBlank()
     */
    #[ORM\Column(
        name: 'name',
        type: 'string',
        length: Constants::LENGTH_LIMIT_TINYTEXT,
        nullable: false,
        options: ['comment' => 'Descriptive name']
    )]
    private string $name = '';

    /**
     * @Identifier()
     * @Assert\NotBlank()
     */
    #[ORM\Column(
        name: 'shortname',
        type: 'string',
        length: Constants::LENGTH_LIMIT_TINYTEXT,
        nullable: false,
        options: ['comment' => 'Short name for this contest']
    )]
    #[Serializer\Groups([AbstractRestController::GROUP_NONSTRICT])]
    private string $shortname = '';

    #[ORM\Column(
        name: 'activatetime',
        type: 'decimal',
        precision: 32,
        scale: 9,
        nullable: false,
        options: ['comment' => 'Time contest becomes visible in team/public views', 'unsigned' => true]
    )]
    #[Serializer\Exclude]
    private string|float $activatetime;

    #[ORM\Column(
        name: 'starttime',
        type: 'decimal',
        precision: 32,
        scale: 9,
        nullable: false,
        options: ['comment' => 'Time contest starts, submissions accepted', 'unsigned' => true]
    )]
    #[Serializer\Exclude]
    private string|float|null $starttime = null;

    #[ORM\Column(
        name: 'starttime_enabled',
        type: 'boolean',
        nullable: false,
        options: ['comment' => 'If disabled, starttime is not used, e.g. to delay contest start', 'default' => 1]
    )]
    #[Serializer\Exclude]
    private bool $starttimeEnabled = true;

    #[ORM\Column(
        name: 'freezetime',
        type: 'decimal',
        precision: 32,
        scale: 9,
        nullable: true,
        options: ['comment' => 'Time scoreboard is frozen', 'unsigned' => true]
    )]
    #[Serializer\Exclude]
    private string|float|null $freezetime = null;

    #[ORM\Column(
        name: 'endtime',
        type: 'decimal',
        precision: 32,
        scale: 9,
        nullable: false,
        options: ['comment' => 'Time after which no more submissions are accepted', 'unsigned' => true]
    )]
    #[Serializer\Exclude]
    private string|float $endtime;

    #[ORM\Column(
        name: 'unfreezetime',
        type: 'decimal',
        precision: 32,
        scale: 9,
        nullable: true,
        options: ['comment' => 'Unfreeze a frozen scoreboard at this time', 'unsigned' => true]
    )]
    #[Serializer\Exclude]
    private string|float|null $unfreezetime = null;

    #[ORM\Column(
        name: 'finalizetime',
        type: 'decimal',
        precision: 32,
        scale: 9,
        nullable: true,
        options: ['comment' => 'Time when contest was finalized, null if not yet', 'unsigned' => true]
    )]
    #[Serializer\Exclude]
    private string|float|null $finalizetime = null;

    #[ORM\Column(
        name: 'finalizecomment',
        type: 'text',
        length: 65535,
        nullable: true,
        options: ['comment' => 'Comments by the finalizer']
    )]
    #[Serializer\Exclude]
    private ?string $finalizecomment = null;

    #[ORM\Column(
        name: 'b',
        type: 'smallint',
        length: 3,
        nullable: false,
        options: ['comment' => 'Number of extra bronze medals', 'unsigned' => true, 'default' => 0]
    )]
    #[Serializer\Exclude]
    private ?int $b = 0;

    #[ORM\Column(
        name: 'medals_enabled',
        type: 'boolean',
        nullable: false,
        options: ['default' => 0]
    )]
    #[Serializer\Exclude]
    private ?bool $medalsEnabled = false;

    #[ORM\JoinTable(name: 'contestteamcategoryformedals')]
    #[ORM\JoinColumn(name: 'cid', referencedColumnName: 'cid', onDelete: 'CASCADE')]
    #[ORM\InverseJoinColumn(name: 'categoryid', referencedColumnName: 'categoryid', onDelete: 'CASCADE')]
    #[ORM\ManyToMany(targetEntity: TeamCategory::class, inversedBy: 'contests_for_medals')]
    #[Serializer\Exclude]
    private Collection $medal_categories;

    #[ORM\Column(
        name: 'gold_medals',
        type: 'smallint',
        length: 3,
        nullable: false,
        options: ['comment' => 'Number of gold medals', 'unsigned' => true, 'default' => 4]
    )]
    #[Serializer\Exclude]
    private int $goldMedals = 4;

    #[ORM\Column(
        name: 'silver_medals',
        type: 'smallint',
        length: 3,
        nullable: false,
        options: ['comment' => 'Number of silver medals', 'unsigned' => true, 'default' => 4]
    )]
    #[Serializer\Exclude]
    private int $silverMedals = 4;

    #[ORM\Column(
        name: 'bronze_medals',
        type: 'smallint',
        length: 3,
        nullable: false,
        options: ['comment' => 'Number of bronze medals', 'unsigned' => true, 'default' => 4]
    )]
    #[Serializer\Exclude]
    private int $bronzeMedals = 4;

    #[ORM\Column(
        name: 'deactivatetime',
        type: 'decimal',
        precision: 32,
        scale: 9,
        nullable: true,
        options: ['comment' => 'Time contest becomes invisible in team/public views', 'unsigned' => true]
    )]
    #[Serializer\Exclude]
    private string|float|null $deactivatetime = null;

    /**
     * @TimeString(relativeIsPositive=false)
     */
    #[ORM\Column(
        name: 'activatetime_string',
        type: 'string',
        length: 64,
        nullable: false,
        options: ['comment' => 'Authoritative absolute or relative string representation of activatetime']
    )]
    #[Serializer\Exclude]
    private string $activatetimeString = '';

    /**
     * @TimeString(allowRelative=false)
     */
    #[ORM\Column(
        name: 'starttime_string',
        type: 'string',
        length: 64,
        nullable: false,
        options: ['comment' => 'Authoritative absolute (only!) string representation of starttime']
    )]
    #[Serializer\Exclude]
    private string $starttimeString = '';

    /**
     * @TimeString()
     */
    #[ORM\Column(
        name: 'freezetime_string',
        type: 'string',
        length: 64,
        nullable: true,
        options: ['comment' => 'Authoritative absolute or relative string representation of freezetime']
    )]
    #[Serializer\Exclude]
    private ?string $freezetimeString = null;

    /**
     * @TimeString()
     */
    #[ORM\Column(
        name: 'endtime_string',
        type: 'string',
        length: 64,
        nullable: false,
        options: ['comment' => 'Authoritative absolute or relative string representation of endtime']
    )]
    #[Serializer\Exclude]
    private string $endtimeString = '';

    /**
     * @TimeString()
     */
    #[ORM\Column(
        name: 'unfreezetime_string',
        type: 'string',
        length: 64,
        nullable: true,
        options: ['comment' => 'Authoritative absolute or relative string representation of unfreezetime']
    )]
    #[Serializer\Exclude]
    private ?string $unfreezetimeString = null;

    /**
     * @TimeString()
     */
    #[ORM\Column(
        name: 'deactivatetime_string',
        type: 'string',
        length: 64,
        nullable: true,
        options: ['comment' => 'Authoritative absolute or relative string representation of deactivatetime']
    )]
    #[Serializer\Exclude]
    private ?string $deactivatetimeString = null;

    #[ORM\Column(
        name: 'enabled',
        type: 'boolean',
        nullable: false,
        options: ['comment' => 'Whether this contest can be active', 'default' => 1]
    )]
    #[Serializer\Exclude]
    private bool $enabled = true;

    #[ORM\Column(
        name: 'allow_submit',
        type: 'boolean',
        nullable: false,
        options: ['comment' => 'Are submissions accepted in this contest?', 'default' => 1]
    )]
    #[Serializer\Groups([AbstractRestController::GROUP_NONSTRICT])]
    private bool $allowSubmit = true;

    #[ORM\Column(
        name: 'process_balloons',
        type: 'boolean',
        nullable: false,
        options: ['comment' => 'Will balloons be processed for this contest?', 'default' => 1]
    )]
    #[Serializer\Exclude]
    private bool $processBalloons = true;

    #[ORM\Column(
        name: 'runtime_as_score_tiebreaker',
        type: 'boolean',
        nullable: false,
        options: ['comment' => 'Is runtime used as tiebreaker instead of penalty?', 'default' => 0]
    )]
    #[Serializer\Groups([AbstractRestController::GROUP_NONSTRICT])]
    private bool $runtime_as_score_tiebreaker = false;

    #[ORM\Column(
        name: 'public',
        type: 'boolean',
        nullable: false,
        options: ['comment' => 'Is this contest visible for the public?', 'default' => 1]
    )]
    #[Serializer\Exclude]
    private bool $public = true;

    /**
     * @Assert\File(mimeTypes={"image/png","image/jpeg","image/svg+xml"}, mimeTypesMessage="Only PNG's, JPG's and SVG's are allowed")
     */
    #[Serializer\Exclude]
    private ?UploadedFile $bannerFile = null;

    #[Serializer\Exclude]
    private bool $clearBanner = false;

    #[ORM\Column(
        name: 'open_to_all_teams',
        type: 'boolean',
        nullable: false,
        options: ['comment' => 'Is this contest open to all teams?', 'default' => 1]
    )]
    #[Serializer\Exclude]
    private bool $openToAllTeams = true;

    #[ORM\Column(
        name: 'warning_message',
        type: 'text',
        length: 65535,
        nullable: true,
        options: ['comment' => 'Warning message for this contest shown on the scoreboards']
    )]
    #[OA\Property(nullable: true)]
    #[Serializer\Groups([AbstractRestController::GROUP_NONSTRICT])]
    private ?string $warningMessage = null;

    #[ORM\Column(
        name: 'is_locked',
        type: 'boolean',
        nullable: false,
        options: ['comment' => 'Is this contest locked for modifications?', 'default' => 0]
    )]
    #[Serializer\Exclude]
    private bool $isLocked = false;

    #[ORM\JoinTable(name: 'contestteam')]
    #[ORM\JoinColumn(name: 'cid', referencedColumnName: 'cid', onDelete: 'CASCADE')]
    #[ORM\InverseJoinColumn(name: 'teamid', referencedColumnName: 'teamid', onDelete: 'CASCADE')]
    #[ORM\ManyToMany(targetEntity: Team::class, inversedBy: 'contests')]
    #[Serializer\Exclude]
    private Collection $teams;

    #[ORM\JoinTable(name: 'contestteamcategory')]
    #[ORM\JoinColumn(name: 'cid', referencedColumnName: 'cid', onDelete: 'CASCADE')]
    #[ORM\InverseJoinColumn(name: 'categoryid', referencedColumnName: 'categoryid', onDelete: 'CASCADE')]
    #[ORM\ManyToMany(targetEntity: TeamCategory::class, inversedBy: 'contests')]
    #[Serializer\Exclude]
    private Collection $team_categories;

    #[ORM\OneToMany(mappedBy: 'contest', targetEntity: Clarification::class)]
    #[Serializer\Exclude]
    private Collection $clarifications;

    #[ORM\OneToMany(mappedBy: 'contest', targetEntity: Submission::class)]
    #[Serializer\Exclude]
    private Collection $submissions;

    /**
     * @Assert\Valid()
     */
    #[ORM\OneToMany(
        mappedBy: 'contest',
        targetEntity: ContestProblem::class,
        cascade: ['persist'],
        orphanRemoval: true)
    ]
    #[ORM\OrderBy(['shortname' => 'ASC'])]
    #[Serializer\Exclude]
    private Collection $problems;

    #[ORM\OneToMany(mappedBy: 'contest', targetEntity: InternalError::class)]
    #[Serializer\Exclude]
    private Collection $internal_errors;

    /**
     * @Assert\Valid()
     */
    #[ORM\OneToMany(mappedBy: 'contest', targetEntity: RemovedInterval::class)]
    #[Serializer\Exclude]
    private Collection $removedIntervals;

    /**
     * @Assert\Valid()
     */
    #[ORM\OneToMany(mappedBy: 'contest', targetEntity: ExternalContestSource::class)]
    #[Serializer\Exclude]
    private Collection $externalContestSources;

    public function __construct()
    {
        $this->problems               = new ArrayCollection();
        $this->teams                  = new ArrayCollection();
        $this->removedIntervals       = new ArrayCollection();
        $this->clarifications         = new ArrayCollection();
        $this->submissions            = new ArrayCollection();
        $this->internal_errors        = new ArrayCollection();
        $this->team_categories        = new ArrayCollection();
        $this->medal_categories       = new ArrayCollection();
        $this->externalContestSources = new ArrayCollection();
    }

    public function getCid(): ?int
    {
        return $this->cid;
    }

    public function setExternalid(?string $externalid): Contest
    {
        $this->externalid = $externalid;
        return $this;
    }

    public function getExternalid(): ?string
    {
        return $this->externalid;
    }

    public function setName(string $name): Contest
    {
        $this->name = $name;
        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setShortname(string $shortname): Contest
    {
        $this->shortname = $shortname;
        return $this;
    }

    public function getShortname(): string
    {
        return $this->shortname;
    }

    public function getShortDescription(): string
    {
        return $this->getShortname();
    }

    public function getActivatetime(): ?float
    {
        return $this->activatetime === null ? null : (float)$this->activatetime;
    }

    public function setStarttime(string|float $starttime): Contest
    {
        $this->starttime = $starttime;
        return $this;
    }

    /**
     * Get starttime, or NULL if disabled.
     *
     * @param bool $nullWhenDisabled If true, return null if the start time is disabled, defaults to true.
     */
    public function getStarttime(bool $nullWhenDisabled = true): ?float
    {
        if ($nullWhenDisabled && !$this->getStarttimeEnabled()) {
            return null;
        }

        return $this->starttime === null ? null : (float)$this->starttime;
    }

    #[Serializer\VirtualProperty]
    #[Serializer\SerializedName('start_time')]
    #[Serializer\Type('DateTime')]
    public function getStartTimeObject(): ?DateTime
    {
        return $this->getStarttime() ? new DateTime(Utils::absTime($this->getStarttime())) : null;
    }

    public function setStarttimeEnabled(bool $starttimeEnabled): Contest
    {
        $this->starttimeEnabled = $starttimeEnabled;
        return $this;
    }

    public function getStarttimeEnabled(): bool
    {
        return $this->starttimeEnabled;
    }

    public function getFreezetime(): ?float
    {
        return $this->freezetime === null ? null : (float)$this->freezetime;
    }

    public function getEndtime(): ?float
    {
        return $this->endtime === null ? null : (float)$this->endtime;
    }

    #[Serializer\VirtualProperty]
    #[Serializer\SerializedName('end_time')]
    #[Serializer\Type('DateTime')]
    #[Serializer\Groups([AbstractRestController::GROUP_NONSTRICT])]
    public function getEndTimeObject(): ?DateTime
    {
        return $this->getEndtime() ? new DateTime(Utils::absTime($this->getEndtime())) : null;
    }

    public function getUnfreezetime(): ?float
    {
        return $this->unfreezetime === null ? null : (float)$this->unfreezetime;
    }

    public function getFinalizetime(): ?float
    {
        return $this->finalizetime === null ? null : (float)$this->finalizetime;
    }

    public function setFinalizetime(string|float|null $finalizetimeString): Contest
    {
        $this->finalizetime = $finalizetimeString;
        return $this;
    }

    public function getFinalizecomment(): ?string
    {
        return $this->finalizecomment;
    }

    public function setFinalizecomment(?string $finalizecomment): Contest
    {
        $this->finalizecomment = $finalizecomment;
        return $this;
    }

    public function getB(): ?int
    {
        return $this->b;
    }

    public function setB(?int $b)
    {
        $this->b = $b;
    }

    public function getDeactivatetime(): ?float
    {
        return $this->deactivatetime === null ? null : (float)$this->deactivatetime;
    }

    public function setActivatetimeString(?string $activatetimeString): Contest
    {
        $this->activatetimeString = $activatetimeString;
        $this->activatetime       = $this->getAbsoluteTime($activatetimeString);
        return $this;
    }

    public function getActivatetimeString(): ?string
    {
        return $this->activatetimeString;
    }

    public function setStarttimeString(string $starttimeString): Contest
    {
        $this->starttimeString = $starttimeString;

        $this->setActivatetimeString($this->getActivatetimeString());
        $this->setFreezetimeString($this->getFreezetimeString());
        $this->setEndtimeString($this->getEndtimeString());
        $this->setUnfreezetimeString($this->getUnfreezetimeString());
        $this->setDeactivatetimeString($this->getDeactivatetimeString());

        return $this;
    }

    public function getStarttimeString(): string
    {
        return $this->starttimeString;
    }

    public function setFreezetimeString(?string $freezetimeString): Contest
    {
        $this->freezetimeString = $freezetimeString;
        $this->freezetime       = $this->getAbsoluteTime($freezetimeString);
        return $this;
    }

    public function getFreezetimeString(): ?string
    {
        return $this->freezetimeString;
    }

    public function setEndtimeString(?string $endtimeString): Contest
    {
        $this->endtimeString = $endtimeString;
        $this->endtime       = $this->getAbsoluteTime($endtimeString);
        return $this;
    }

    public function getEndtimeString(): ?string
    {
        return $this->endtimeString;
    }

    public function setUnfreezetimeString(?string $unfreezetimeString): Contest
    {
        $this->unfreezetimeString = $unfreezetimeString;
        $this->unfreezetime       = $this->getAbsoluteTime($unfreezetimeString);
        return $this;
    }

    public function getUnfreezetimeString(): ?string
    {
        return $this->unfreezetimeString;
    }

    /**
     * @Serializer\VirtualProperty()
     * @Serializer\SerializedName("scoreboard_thaw_time")
     * @Serializer\Type("DateTime")
     */
    public function getUnfreezeTimeObject(): ?DateTime
    {
        return $this->getUnfreezetime() ? new DateTime(Utils::absTime($this->getUnfreezetime())) : null;
    }

    public function setDeactivatetimeString(?string $deactivatetimeString): Contest
    {
        $this->deactivatetimeString = $deactivatetimeString;
        $this->deactivatetime       = $this->getAbsoluteTime($deactivatetimeString);
        return $this;
    }

    public function getDeactivatetimeString(): ?string
    {
        return $this->deactivatetimeString;
    }

    public function setActivatetime(string $activatetime): Contest
    {
        $this->activatetime = $activatetime;
        return $this;
    }

    public function setFreezetime(string $freezetime): Contest
    {
        $this->freezetime = $freezetime;
        return $this;
    }

    public function setEndtime(string $endtime): Contest
    {
        $this->endtime = $endtime;
        return $this;
    }

    /**
     * @param string|float $unfreezetime
     */
    public function setUnfreezetime($unfreezetime): Contest
    {
        $this->unfreezetime = $unfreezetime;
        return $this;
    }

    public function setDeactivatetime(string $deactivatetime): Contest
    {
        $this->deactivatetime = $deactivatetime;
        return $this;
    }

    public function setEnabled(bool $enabled): Contest
    {
        $this->enabled = $enabled;
        return $this;
    }

    public function getEnabled(): bool
    {
        return $this->enabled;
    }

    public function setAllowSubmit(bool $allowSubmit): Contest
    {
        $this->allowSubmit = $allowSubmit;
        return $this;
    }

    public function getAllowSubmit(): bool
    {
        return $this->allowSubmit;
    }

    public function getWarningMessage(): ?string
    {
        return $this->warningMessage;
    }

    public function setWarningMessage(?string $warningMessage): Contest
    {
        $this->warningMessage = (empty($warningMessage) ? null : $warningMessage);
        return $this;
    }

    public function setProcessBalloons(bool $processBalloons): Contest
    {
        $this->processBalloons = $processBalloons;
        return $this;
    }

    public function getProcessBalloons(): bool
    {
        return $this->processBalloons;
    }

    public function setRuntimeAsScoreTiebreaker(bool $runtimeAsScoreTiebreaker): Contest
    {
        $this->runtime_as_score_tiebreaker = $runtimeAsScoreTiebreaker;
        return $this;
    }

    public function getRuntimeAsScoreTiebreaker(): bool
    {
        return $this->runtime_as_score_tiebreaker;
    }

    public function setMedalsEnabled(bool $medalsEnabled): Contest
    {
        $this->medalsEnabled = $medalsEnabled;
        return $this;
    }

    public function getMedalsEnabled(): bool
    {
        return $this->medalsEnabled;
    }

    /**
     * @return Collection|TeamCategory[]
     */
    public function getMedalCategories(): Collection
    {
        return $this->medal_categories;
    }

    public function addMedalCategory(TeamCategory $medalCategory): Contest
    {
        if (!$this->medal_categories->contains($medalCategory)) {
            $this->medal_categories[] = $medalCategory;
        }

        return $this;
    }

    public function removeMedalCategories(TeamCategory $medalCategory): Contest
    {
        if ($this->medal_categories->contains($medalCategory)) {
            $this->medal_categories->removeElement($medalCategory);
        }

        return $this;
    }

    public function setGoldMedals(int $goldMedals): Contest
    {
        $this->goldMedals = $goldMedals;
        return $this;
    }

    public function getGoldMedals(): int
    {
        return $this->goldMedals;
    }

    public function setSilverMedals(int $silverMedals): Contest
    {
        $this->silverMedals = $silverMedals;
        return $this;
    }

    public function getSilverMedals(): int
    {
        return $this->silverMedals;
    }

    public function setBronzeMedals(int $bronzeMedals): Contest
    {
        $this->bronzeMedals = $bronzeMedals;
        return $this;
    }

    public function getBronzeMedals(): int
    {
        return $this->bronzeMedals;
    }

    public function setPublic(bool $public): Contest
    {
        $this->public = $public;
        return $this;
    }

    public function getPublic(): bool
    {
        return $this->public;
    }

    public function setOpenToAllTeams(bool $openToAllTeams): Contest
    {
        $this->openToAllTeams = $openToAllTeams;
        if ($this->openToAllTeams) {
            $this->teams->clear();
            $this->team_categories->clear();
        }

        return $this;
    }

    public function isOpenToAllTeams(): bool
    {
        return $this->openToAllTeams;
    }

    public function isLocked(): bool
    {
        return $this->isLocked;
    }

    public function setIsLocked(bool $isLocked): Contest
    {
        $this->isLocked = $isLocked;
        return $this;
    }

    public function addTeam(Team $team): Contest
    {
        $this->teams[] = $team;
        return $this;
    }

    public function removeTeam(Team $team): void
    {
        $this->teams->removeElement($team);
    }

    public function getTeams(): Collection
    {
        return $this->teams;
    }

    public function addProblem(ContestProblem $problem): Contest
    {
        $this->problems[] = $problem;
        return $this;
    }

    public function removeProblem(ContestProblem $problem): void
    {
        $this->problems->removeElement($problem);
    }

    public function getProblems(): Collection
    {
        return $this->problems;
    }

    public function addClarification(Clarification $clarification): Contest
    {
        $this->clarifications[] = $clarification;
        return $this;
    }

    public function removeClarification(Clarification $clarification): void
    {
        $this->clarifications->removeElement($clarification);
    }

    public function getClarifications(): Collection
    {
        return $this->clarifications;
    }

    public function addSubmission(Submission $submission): Contest
    {
        $this->submissions[] = $submission;
        return $this;
    }

    public function removeSubmission(Submission $submission): void
    {
        $this->submissions->removeElement($submission);
    }

    public function getSubmissions(): Collection
    {
        return $this->submissions;
    }

    public function addInternalError(InternalError $internalError): Contest
    {
        $this->internal_errors[] = $internalError;
        return $this;
    }

    public function removeInternalError(InternalError $internalError): void
    {
        $this->internal_errors->removeElement($internalError);
    }

    public function getInternalErrors(): Collection
    {
        return $this->internal_errors;
    }

    #[Serializer\VirtualProperty]
    #[Serializer\Type('string')]
    public function getDuration(): string
    {
        return Utils::relTime($this->getEndtime() - $this->starttime);
    }

    #[OA\Property(nullable: true)]
    #[Serializer\VirtualProperty]
    #[Serializer\Type('string')]
    public function getScoreboardFreezeDuration(): ?string
    {
        if (!empty($this->getFreezetime())) {
            return Utils::relTime($this->getEndtime() - $this->getFreezetime());
        } else {
            return null;
        }
    }

    /**
     * Returns true iff the contest is already and still active, and not disabled.
     */
    public function isActive(): bool
    {
        return $this->getEnabled() &&
            $this->getPublic() &&
            ($this->activatetime <= time()) &&
            ($this->deactivatetime == null || $this->deactivatetime > time());
    }

    public function getAbsoluteTime(?string $time_string): float|int|string|null
    {
        if ($time_string === null) {
            return null;
        } elseif (preg_match('/^[+-][0-9]+:[0-9]{2}(:[0-9]{2}(\.[0-9]{0,6})?)?$/', $time_string)) {
            $sign           = ($time_string[0] == '-' ? -1 : +1);
            $time_string[0] = 0;
            $times          = explode(':', $time_string, 3);
            $hours          = (int)$times[0];
            $minutes        = (int)$times[1];
            if (count($times) == 2) {
                $seconds = 0;
            } else {
                $seconds = (float)$times[2];
            }
            $seconds      = $seconds + 60 * ($minutes + 60 * $hours);
            $seconds      *= $sign;
            $absoluteTime = $this->starttime + $seconds;

            // Take into account the removed intervals.
            /** @var RemovedInterval[] $removedIntervals */
            $removedIntervals = $this->getRemovedIntervals()->toArray();
            usort(
                $removedIntervals,
                static fn(
                    RemovedInterval $a,
                    RemovedInterval $b
                ) => Utils::difftime((float)$a->getStarttime(), (float)$b->getStarttime())
            );
            foreach ($removedIntervals as $removedInterval) {
                if (Utils::difftime((float)$removedInterval->getStarttime(), (float)$absoluteTime) <= 0) {
                    $absoluteTime += Utils::difftime((float)$removedInterval->getEndtime(),
                                                     (float)$removedInterval->getStarttime());
                }
            }

            return $absoluteTime;
        } else {
            try {
                $date = new DateTime($time_string);
            } catch (Exception) {
                return null;
            }
            return $date->format('U.v');
        }
    }

    public function addRemovedInterval(RemovedInterval $removedInterval): Contest
    {
        $this->removedIntervals->add($removedInterval);
        return $this;
    }

    public function removeRemovedInterval(RemovedInterval $removedInterval): void
    {
        $this->removedIntervals->removeElement($removedInterval);
    }

    public function getRemovedIntervals(): Collection
    {
        return $this->removedIntervals;
    }

    public function getContestTime(float $wallTime): float
    {
        $contestTime = Utils::difftime($wallTime, (float)$this->getStarttime(false));
        /** @var RemovedInterval $removedInterval */
        foreach ($this->getRemovedIntervals() as $removedInterval) {
            if (Utils::difftime((float)$removedInterval->getStarttime(), $wallTime) < 0) {
                $contestTime -= min(
                    Utils::difftime($wallTime, (float)$removedInterval->getStarttime()),
                    Utils::difftime((float)$removedInterval->getEndtime(), (float)$removedInterval->getStarttime())
                );
            }
        }

        return $contestTime;
    }

    public function getDataForJuryInterface(): array
    {
        $now         = Utils::now();
        $times       = ['activate', 'start', 'freeze', 'end', 'unfreeze', 'finalize', 'deactivate'];
        $prevchecked = false;
        $isactivated = Utils::difftime((float)$this->getActivatetime(), $now) <= 0;
        $hasstarted  = Utils::difftime((float)$this->getStarttime(), $now) <= 0;
        $hasended    = Utils::difftime((float)$this->getEndtime(), $now) <= 0;
        $hasfrozen   = !empty($this->getFreezetime()) &&
            Utils::difftime((float)$this->getFreezetime(), $now) <= 0;
        $hasunfrozen = !empty($this->getUnfreezetime()) &&
            Utils::difftime((float)$this->getUnfreezetime(), $now) <= 0;
        $isfinal     = !empty($this->getFinalizetime());

        if (!$this->getStarttimeEnabled()) {
            $hasstarted = $hasended = $hasfrozen = $hasunfrozen = false;
        }

        $result = [];
        foreach ($times as $time) {
            $resultItem = [];
            $method     = sprintf('get%stime', ucfirst($time));
            $timeValue  = $this->{$method}();
            if ($time === 'start' && !$this->getStarttimeEnabled()) {
                $resultItem['icon'] = 'ellipsis-h';
                $timeValue          = $this->getStarttime(false);
                $prevchecked        = false;
            } elseif (empty($timeValue)) {
                $resultItem['icon'] = null;
            } elseif (Utils::difftime((float)$timeValue, $now) <= 0) {
                // This event has passed, mark as such.
                $resultItem['icon'] = 'check';
                $prevchecked        = true;
            } elseif ($prevchecked) {
                $resultItem['icon'] = 'ellipsis-h';
                $prevchecked        = false;
            }

            $resultItem['label'] = sprintf('%s time', ucfirst($time));
            $resultItem['time']  = Utils::printtime($timeValue, 'Y-m-d H:i:s (T)');
            if ($time === 'start' && !$this->getStarttimeEnabled()) {
                $resultItem['class'] = 'ignore';
            }

            $showButton = true;
            switch ($time) {
                case 'activate':
                    $showButton = !$isactivated;
                    break;
                case 'start':
                    $showButton = !$hasstarted;
                    break;
                case 'end':
                    $showButton = $hasstarted && !$hasended && (empty($this->getFreezetime()) || $hasfrozen);
                    break;
                case 'deactivate':
                    $showButton = $hasended && (empty($this->getUnfreezetime()) || $hasunfrozen);
                    break;
                case 'freeze':
                    $showButton = $hasstarted && !$hasended && !$hasfrozen;
                    break;
                case 'unfreeze':
                    $showButton = $hasfrozen && !$hasunfrozen && $hasended;
                    break;
                case 'finalize':
                    $showButton = $hasended && !$isfinal;
                    break;
            }

            $resultItem['show_button'] = $showButton;

            $closeToStart = Utils::difftime((float)$this->starttime,
                                            $now) <= self::STARTTIME_UPDATE_MIN_SECONDS_BEFORE;
            if ($time === 'start' && !$closeToStart) {
                $type                       = $this->getStarttimeEnabled() ? 'delay' : 'resume';
                $resultItem['extra_button'] = [
                    'type' => $type . '_start',
                    'label' => $type . ' start',
                ];
            }

            $result[$time] = $resultItem;
        }

        return $result;
    }

    public function getState(): ?array
    {
        $time_or_null             = function ($time, $extra_cond = true) {
            if (!$extra_cond || $time === null || Utils::now() < $time) {
                return null;
            }
            return Utils::absTime($time);
        };
        $result                   = [];
        $result['started']        = $time_or_null($this->getStarttime());
        $result['ended']          = $time_or_null($this->getEndtime(), $result['started'] !== null);
        $result['frozen']         = $time_or_null($this->getFreezetime(), $result['started'] !== null);
        $result['thawed']         = $time_or_null($this->getUnfreezetime(), $result['frozen'] !== null);
        $result['finalized']      = $time_or_null($this->getFinalizetime(), $result['ended'] !== null);
        $result['end_of_updates'] = null;
        if ($result['finalized'] !== null &&
            ($result['thawed'] !== null || $result['frozen'] === null)) {
            if ($result['thawed'] !== null &&
                $this->getFreezetime() > $this->getFinalizetime()) {
                $result['end_of_updates'] = $result['thawed'];
            } else {
                $result['end_of_updates'] = $result['finalized'];
            }
        }
        return $result;
    }

    public function getMinutesRemaining(): int
    {
        return (int)floor(($this->getEndtime() - $this->getFreezetime()) / 60);
    }

    public function getFreezeData(): FreezeData
    {
        return new FreezeData($this);
    }

    #[ORM\PrePersist]
    #[ORM\PreUpdate]
    public function updateTimes(): void
    {
        // Update the start times, as this will update all other fields.
        $this->setStarttime((float)strtotime($this->getStarttimeString()));
        $this->setStarttimeString($this->getStarttimeString());
    }

    /**
     * @Assert\Callback()
     */
    public function validate(ExecutionContextInterface $context): void
    {
        $this->updateTimes();
        if (Utils::difftime((float)$this->getEndtime(), (float)$this->getStarttime(true)) <= 0) {
            $context
                ->buildViolation('Contest ends before it even starts')
                ->atPath('endtimeString')
                ->addViolation();
        }
        if (!empty($this->getFreezetime())) {
            if (Utils::difftime((float)$this->getFreezetime(), (float)$this->getEndtime()) > 0 ||
                Utils::difftime((float)$this->getFreezetime(), (float)$this->getStarttime()) < 0) {
                $context
                    ->buildViolation('Freezetime is out of start/endtime range')
                    ->atPath('freezetimeString')
                    ->addViolation();
            }
        }
        if (Utils::difftime((float)$this->getActivatetime(), (float)$this->getStarttime(false)) > 0) {
            $context
                ->buildViolation('Activate time is later than starttime')
                ->atPath('activatetimeString')
                ->addViolation();
        }
        if (!empty($this->getUnfreezetime())) {
            if (empty($this->getFreezetime())) {
                $context
                    ->buildViolation('Unfreezetime set but no freeze time. That makes no sense.')
                    ->atPath('unfreezetimeString')
                    ->addViolation();
            }
            if (Utils::difftime((float)$this->getUnfreezetime(), (float)$this->getEndtime()) < 0) {
                $context
                    ->buildViolation('Unfreezetime must be larger than endtime.')
                    ->atPath('unfreezetimeString')
                    ->addViolation();
            }
            if (!empty($this->getDeactivatetime()) &&
                Utils::difftime((float)$this->getDeactivatetime(), (float)$this->getUnfreezetime()) < 0) {
                $context
                    ->buildViolation('Deactivatetime must be larger than unfreezetime.')
                    ->atPath('deactivatetimeString')
                    ->addViolation();
            }
        } else {
            if (!empty($this->getDeactivatetime()) &&
                Utils::difftime((float)$this->getDeactivatetime(), (float)$this->getEndtime()) < 0) {
                $context
                    ->buildViolation('Deactivatetime must be larger than endtime.')
                    ->atPath('deactivatetimeString')
                    ->addViolation();
            }
        }

        if ($this->medalsEnabled) {
            foreach (['goldMedals', 'silverMedals', 'bronzeMedals'] as $field) {
                if ($this->$field === null) {
                    $context
                        ->buildViolation('This field is required when \'Enable medals\' is set.')
                        ->atPath($field)
                        ->addViolation();
                }
            }
            if ($this->medal_categories === null || $this->medal_categories->isEmpty()) {
                $context
                    ->buildViolation('This field is required when \'Process medals\' is set.')
                    ->atPath('medalCategories')
                    ->addViolation();
            }
        }

        /** @var ContestProblem $problem */
        foreach ($this->problems as $idx => $problem) {
            // Check if the problem ID is unique.
            $otherProblemIds = $this->problems
                ->filter(fn(ContestProblem $otherProblem) => $otherProblem !== $problem)
                ->map(fn(ContestProblem $problem) => $problem->getProblem()->getProbid())
                ->toArray();
            $problemId       = $problem->getProblem()->getProbid();
            if (in_array($problemId, $otherProblemIds)) {
                $context
                    ->buildViolation('Each problem can only be added to a contest once')
                    ->atPath(sprintf('problems[%d].problem', $idx))
                    ->addViolation();
            }

            // Check if the problem shortname is unique.
            $otherShortNames = $this->problems
                ->filter(fn(ContestProblem $otherProblem) => $otherProblem !== $problem)
                ->map(fn(ContestProblem $problem) => strtolower($problem->getShortname()))
                ->toArray();
            $shortname = strtolower($problem->getShortname());
            if (in_array($shortname, $otherShortNames)) {
                $context
                    ->buildViolation('Each shortname should be unique within a contest')
                    ->atPath(sprintf('problems[%d].shortname', $idx))
                    ->addViolation();
            }
        }
    }

    /**
     * Return whether a (wall clock) time falls within the contest.
     */
    public function isTimeInContest(float $time): bool
    {
        return Utils::difftime((float)$this->getStarttime(), $time) <= 0 &&
               Utils::difftime((float)$this->getEndtime(), $time) > 0;
    }

    public function getCountdownString(): string
    {
        $now = Utils::now();
        if (Utils::difftime((float)$this->getActivatetime(), $now) <= 0) {
            if (!$this->getStarttimeEnabled()) {
                return 'start delayed';
            }
            if ($this->isTimeInContest($now)) {
                return Utils::printtimediff($now, (float)$this->getEndtime());
            } elseif (Utils::difftime((float)$this->getStarttime(), $now) >= 0) {
                return 'time to start: ' . Utils::printtimediff($now, (float)$this->getStarttime());
            }
        }

        return '';
    }

    public function getOpenToAllTeams(): ?bool
    {
        return $this->openToAllTeams;
    }

    /**
     * @return Collection|TeamCategory[]
     */
    public function getTeamCategories(): Collection
    {
        return $this->team_categories;
    }

    public function addTeamCategory(TeamCategory $teamCategory): self
    {
        if (!$this->team_categories->contains($teamCategory)) {
            $this->team_categories[] = $teamCategory;
        }

        return $this;
    }

    public function removeTeamCategory(TeamCategory $teamCategory): self
    {
        if ($this->team_categories->contains($teamCategory)) {
            $this->team_categories->removeElement($teamCategory);
        }

        return $this;
    }

    /**
     * @return Collection|ExternalContestSource[]
     */
    public function getExternalContestSources(): Collection
    {
        return $this->externalContestSources;
    }

    public function addExternalContestSource(ExternalContestSource $externalContestSource): self
    {
        if (!$this->externalContestSources->contains($externalContestSource)) {
            $this->externalContestSources[] = $externalContestSource;
        }

        return $this;
    }

    public function removeExternalContestSource(ExternalContestSource $externalContestSource): self
    {
        if ($this->externalContestSources->contains($externalContestSource)) {
            $this->externalContestSources->removeElement($externalContestSource);
        }

        return $this;
    }

    public function getBannerFile(): ?UploadedFile
    {
        return $this->bannerFile;
    }

    public function setBannerFile(?UploadedFile $bannerFile): Contest
    {
        $this->bannerFile = $bannerFile;
        return $this;
    }

    public function isClearBanner(): bool
    {
        return $this->clearBanner;
    }

    public function setClearBanner(bool $clearBanner): Contest
    {
        $this->clearBanner = $clearBanner;
        return $this;
    }

    public function getAssetProperties(): array
    {
        return ['banner'];
    }

    public function getAssetFile(string $property): ?UploadedFile
    {
        return match ($property) {
            'banner' => $this->getBannerFile(),
            default => null,
        };
    }

    public function isClearAsset(string $property): ?bool
    {
        return match ($property) {
            'banner' => $this->isClearBanner(),
            default => null,
        };
    }
}
