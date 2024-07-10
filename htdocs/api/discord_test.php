<?php

require "../../vendor/autoload.php";
require "../../lib/lifestream.inc.php";

use PhpChannels\DiscordWebhook\Discord;

/*
Array
(
    [thumb] => Array
        (
            [name] => thumb.jpg
            [full_path] => thumb.jpg
            [type] => image/jpeg
            [tmp_name] => /tmp/phpCKihg9
            [error] => 0
            [size] => 7665
        )

)

*/

$payload_json = <<<EOW
{
  "event":"library.new",
  "user":true,
  "owner":true,
  "Account":{
     "id":556205,
     "thumb":"https://plex.tv/users/72e157121c9458d7/avatar?c=1661426427",
     "title":"Aquarion"
  },
  "Server":{
     "title":"Vis",
     "uuid":"7b64abb13817a3b8c3a0776af127a001ea0b9ec7"
  },
  "Metadata":{
     "librarySectionType":"show",
     "ratingKey":"57612",
     "key":"/library/metadata/57612",
     "parentRatingKey":"55320",
     "grandparentRatingKey":"55319",
     "guid":"plex://episode/5fbdc9a1bd6a1c002deb79cc",
     "parentGuid":"plex://season/602e754e67f4c8002ce54b48",
     "grandparentGuid":"plex://show/5d9c090e705e7a001e6e94d8",
     "type":"episode",
     "title":"Baby Race",
     "grandparentKey":"/library/metadata/55319",
     "parentKey":"/library/metadata/55320",
     "librarySectionTitle":"TV Shows",
     "librarySectionID":5,
     "librarySectionKey":"/library/sections/5",
     "grandparentTitle":"Bluey (2018)",
     "parentTitle":"Season 2",
     "contentRating":"TV-Y",
     "summary":"Mum remembers when Bluey and Judo were babies and their race to see who would walk first! PLAY: https://www.bbc.co.uk/iplayer/episode/m001j05sINFO: https://www.bbc.co.uk/programmes/m001j05s",
     "index":50,
     "parentIndex":2,
     "year":2023,
     "thumb":"/library/metadata/57612/thumb/1686923356",
     "art":"/library/metadata/55319/art/1685938650",
     "parentThumb":"/library/metadata/55320/thumb/1679236691",
     "grandparentThumb":"/library/metadata/55319/thumb/1685938650",
     "grandparentArt":"/library/metadata/55319/art/1685938650",
     "grandparentTheme":"/library/metadata/55319/theme/1685938650",
     "duration":420000,
     "originallyAvailableAt":"2023-02-08",
     "addedAt":1686923355,
     "updatedAt":1686923356,
     "Guid":[
        {
           "id":"imdb://tt13429908"
        },
        {
           "id":"tmdb://2499236"
        },
        {
           "id":"tvdb://8031280"
        }
     ],
     "Writer":[
        {
           "id":108188,
           "filter":"writer=108188",
           "tag":"Joe Brumm",
           "tagKey":"5d776bb6ad5437001f7a7a76",
           "thumb":"https://metadata-static.plex.tv/5/people/5d4eb7c932364a2320988196644d70ae.jpg"
        }
     ],
     "Role":[
        {
           "id":108095,
           "filter":"actor=108095",
           "tag":"Grant Sundin",
           "tagKey":"61c08f8e8810917dfd02383e",
           "role":"Doctor (voice)"
        },
        {
           "id":108064,
           "filter":"actor=108064",
           "tag":"Beth Durack",
           "tagKey":"61c08f8e6fe1ffe477a515ee",
           "role":"Wendy (voice)",
           "thumb":"https://metadata-static.plex.tv/7/people/7cc6cbd149889df0cbb75eb905d1814e.jpg"
        },
        {
           "id":108060,
           "filter":"actor=108060",
           "tag":"Chris Brumm",
           "tagKey":"61bc9fcbde017c5ef4a8243c",
           "role":"Nana (voice)"
        },
        {
           "id":108079,
           "filter":"actor=108079",
           "tag":"Vikki Ong",
           "tagKey":"61c08f8e8282cd5bf3c7441e",
           "role":"Snickers' Mum (voice)"
        },
        {
           "id":108063,
           "filter":"actor=108063",
           "tag":"Leigh Sales",
           "tagKey":"5d7770b231d95e001f1a7af3",
           "role":"Bella (voice)",
           "thumb":"https://metadata-static.plex.tv/1/people/15dd00d15e070b8e090385dcc5563329.jpg"
        }
     ]
  }
}
EOW;

$payload = json_decode($payload_json);

// echo json_last_error_msg() ;

// echo "<hr/>";

// var_dump($payload_json);
// echo "<hr/>";
// var_dump($payload);
// echo "<hr/>";

$message = Discord::message('https://discord.com/api/webhooks/1119265413579157534/VYOxI6Vws6giGjq7SsRoqblK89QxNAuScZgBhS6CqOgHegdy4LB9pqqY1MLmL9988VCK')
    ->setUsername('Vis');

switch ($payload->Metadata->librarySectionType) {
    case 'show':
        $show = $payload->Metadata->grandparentTitle;
        $series = $payload->Metadata->parentTitle;
        $episode = $payload->Metadata->title;
        $message->setTitle("New $show $series Episode: $episode");
        $message->setDescription($payload->Metadata->summary);
        $message->setThumbnail('https://app.plex.tv/9e432efb-371e-4bb6-b293-40806320701f0');
        # code...
        break;

    default:
        # code...
        break;
}

$message->send();

#    ->setContent('')
#    ->send();
