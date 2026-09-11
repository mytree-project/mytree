<?php

declare(strict_types=1);

namespace App\Domain\Acquisition;

enum PredicateKey: string
{
    case PersonGivenName = 'person.given_name';
    case PersonSurname = 'person.surname';
    case PersonAge = 'person.age';
    case PersonBirthDate = 'person.birth_date';
    case PersonDeathDate = 'person.death_date';
    case PersonParent = 'person.parent';
    case PersonSpouse = 'person.spouse';
    case PersonBirthPlace = 'person.birth_place';
    case PersonResidence = 'person.residence';
    case PersonPermanentResidence = 'person.permanent_residence';
    case PersonTemporaryStay = 'person.temporary_stay';
    case PersonPresence = 'person.presence';
    case PersonAddress = 'person.address';
    case PersonOrigin = 'person.origin';
    case PersonWorkPlace = 'person.work_place';
    case PersonStudyPlace = 'person.study_place';
    case PersonDetentionPlace = 'person.detention_place';
    case PersonExilePlace = 'person.exile_place';
    case PersonDeportationDestination = 'person.deportation_destination';
    case PersonOccupation = 'person.occupation';
    case PersonSocialStatus = 'person.social_status';
    case PersonSocialEstate = 'person.social_estate';
    case PersonOffice = 'person.office';
    case PersonRank = 'person.rank';
    case PersonTitle = 'person.title';
    case PersonAcademicDegree = 'person.academic_degree';

    case EventDate = 'event.date';
    case EventPlace = 'event.place';
    case EventParticipant = 'event.participant';
    case EventChild = 'event.child';
    case EventParent = 'event.parent';
    case EventSpouse = 'event.spouse';
    case EventWitness = 'event.witness';
    case EventDeclarant = 'event.declarant';
    case EventOfficiant = 'event.officiant';
    case EventOriginPlace = 'event.origin_place';
    case EventDestinationPlace = 'event.destination_place';
    case EventReason = 'event.reason';

    case PlaceName = 'place.name';
}
