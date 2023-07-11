<?php
header('Access-Control-Allow-Origin: *'); // Allow requests from any origin
header('Access-Control-Allow-Methods: GET, POST, OPTIONS'); // Allow specific HTTP methods
header('Access-Control-Allow-Headers: Content-Type, Authorization'); // Allow specific headers

include_once 'php/connection.php';

$channelName = $_GET['channelName'];

$API_KEY = 'AIzaSyCtNR231iSaufXFJIZa90m9ScV9nKP9bNw';
$allResults = [];

//this will check if the search input is empty or not
if(!empty($channelName)){
    try{
        // find the channel id of the given channel name based on the input searched by the user
        $channelID = file_get_contents("https://www.googleapis.com/youtube/v3/search?part=snippet&type=channel&maxResults=1&q=" . urlencode($channelName) . "&key=" . urlencode($API_KEY));
        $channelID = json_decode($channelID, true);
        $channelIdFinal = $channelID['items'][0]['id']['channelId'];
        $channeNameFinal = $channelID['items'][0]['snippet']['channelTitle'];
        $description = $channelID['items'][0]['snippet']['description'];
        $thumbnailUrl = $channelID['items'][0]['snippet']['thumbnails']['high']['url'];

        //after getting the channel id the the program will sesarch for it in the database if there is a 
        //channel name similar to the channel name that the user wants to find
        $validateChannelNameIfExist = "SELECT * FROM youtube_channel WHERE name = :channelName";
        $statement = $pdo->prepare($validateChannelNameIfExist);
        $statement->bindParam(':channelName', $channeNameFinal, PDO::PARAM_STR);
        $statement->execute();
        
        //if found same channel name then there is no need to get for the videos since there is already videos stored in the databasae
        // this means that if there is already similar channel in the database then therefore the vidoes is already been saved in the database
        // this will just simply return the channel id into the dashboard vue to be used later for displaying channel information
        if ($statement->rowCount() > 0) {
            $getChannel = $pdo->prepare("SELECT * FROM youtube_channel INNER JOIN youtube_channel_videos ON youtube_channel.id = youtube_channel_videos.channel_id WHERE youtube_channel.name = :channelName");
            $getChannel->bindParam(':channelName', $channeNameFinal, PDO::PARAM_STR);
            $getChannel->execute();
        
            if ($getChannel) {
                $rows = $getChannel->fetchAll(PDO::FETCH_ASSOC);
                $channelInformation = json_encode($rows, JSON_PRETTY_PRINT);
        
                header('Content-Type: application/json');
        
                echo $channelIdFinal;
            } else {
                echo 'Error';
            }
        } 
        
        // if there is no channel found in the database similar to searched input of the user 
        // then therefore the channel information being retrieve above will be saved into the database
        else {
            try { 
                // this will saved the channel information
                $result = storeYoutubeChannelInformation($channelIdFinal, $thumbnailUrl, $channeNameFinal, $description, $pdo);
                if ($result) {
                    // after saving the channel information then the program will retrieve all the first latest 100 videos usign the channelID beign obtained above
                    // this will first get the first 50 videos since the max videos per page is 50
                    $channelResponse1 = file_get_contents("https://www.googleapis.com/youtube/v3/search?order=date&part=snippet&channelId=" . urlencode($channelIdFinal) . "&maxResults=50&key=" . urlencode($API_KEY));
                    $channelResponse1 = json_decode($channelResponse1, true);
                    $totalVideos = $channelResponse1['pageInfo']['totalResults'];
                    $allResults = $channelResponse1['items'];

                    //check if there is still nextpage by checking if there is a nextPageToken
                    //the while will query the reamining videos needed since the we only have 50 yet and we 
                    // need  to get another 50 to make it 100 and this will only be valid if the total videos in that channel is greater than 100
                    while(count($allResults) < 100 && isset($channelResponse1['nextPageToken'])){
                        $nextPageToken = $channelResponse1['nextPageToken'];
                        $channelResponse2 = file_get_contents("https://www.googleapis.com/youtube/v3/search?order=date&part=snippet&channelId=" . urlencode($channelIdFinal) . "&maxResults=50&pageToken=" . urlencode($nextPageToken) . "&key=" . urlencode($API_KEY));
                        $channelResponse2 = json_decode($channelResponse2, true);
                        $allResults = array_merge($allResults, $channelResponse2['items']);
                        $channelResponse1 = $channelResponse2;
                    }
                      
                    //once all the videos is stored in an array this will be processed
                    $resultOfProcessVideos = processVideos($allResults);
                    $jsonResults = json_encode($resultOfProcessVideos, JSON_PRETTY_PRINT);
                    $resultsArray = json_decode($jsonResults);
                    $allVideos = array_filter($resultsArray); // this is to check if there is a null in the arrays

                    // this will count the total null values
                    // if there is null found then therefore we need to erase that and change it with a new one depending on how much null is found
                    $nullCount = 100 - count($allVideos); 
                    $nullCountBefore = $nullCount;
                    $newResult = [];

                    $videosCountBefore = count($resultOfProcessVideos);
                    $videosCountNow = 0;
                    

                    // this will first check if the channel has a videos greater than 100
                    // because if the channel videos is lower than 100 then there is no need to replace those null values with a new one
                    // since there is nothing to add

                    if($totalVideos > 100){

                        // this will execute untill all the null values is being replaced by new one
                        //and this will jsut continue as long as there is a null value and there is a next page token
                        while($nullCount > 0 && isset($channelResponse1['nextPageToken'])){
                            $allVideos = array_filter($resultsArray);
    
                            while(count($newResult) < $nullCount && isset($channelResponse1['nextPageToken'])){
                                $nextPageToken = $channelResponse1['nextPageToken'];
                                $channelResponse3 = file_get_contents("https://www.googleapis.com/youtube/v3/search?order=date&part=snippet&channelId=" . urlencode($channelIdFinal) . "&maxResults=50&pageToken=" . urlencode($nextPageToken) . "&key=" . urlencode($API_KEY));
                                $channelResponse3 = json_decode($channelResponse3, true);
                                $newResult = array_merge($newResult, $channelResponse3['items']);
                                $channelResponse1 = $channelResponse3;
                            }

                            $resultOfProcessVideos = processVideos($newResult);
                            $jsonResultsRemainingVideos = json_encode($resultOfProcessVideos);
                            $resultsArrayRemainingVideos = json_decode($jsonResultsRemainingVideos, true);
                            $allVideosRemainingVideos = array_filter($resultsArrayRemainingVideos);
    
                            if(count($allVideosRemainingVideos) >= $nullCount){
                                $allVideosSliced = array_slice($allVideosRemainingVideos, 0, $nullCount);
                                $allVideos = array_merge($allVideos, $allVideosSliced);
                                $jsonResultsRemainingVideosFiltered = json_encode($allVideosSliced);
                                $videosCountNow = count($allVideosSliced);

                                $nullCount = 0;
                            }

                            else{
                                $nullCount = $nullCount - count($jsonResults);
                            }                            
                        }
                    }

                    else{
                        $allVideos = array_filter($resultsArray);
                    }

                    $jsonResultsFinal = json_encode($allVideos, JSON_PRETTY_PRINT);
                    
                    header('Content-Type: application/json');

                    // if all the videos was stored then the program will store those videos in the database
                    //after storing it in the database the program will return the channel ID which will be used later to display channel information
                    $saveVideoResult = storeChannelVideos($channelIdFinal, $jsonResultsFinal, $pdo);
                    if ($saveVideoResult) {
                        echo $channelIdFinal;
                    }
        
                    else{
                        echo "Error";
                    }
                } 
                
                else {
                    // Execution failed
                    echo "Error";
                }
        
            } catch (Exception $e) {
                echo 'Error';
            }
        }
    } catch (Exception $e){
        echo 'Error';
    }
}

else{
    echo 'Error';
}

function storeYoutubeChannelInformation($channelID1, $image, $name, $description, $pdo){
    $saveYoutubeChannelInformation = "INSERT INTO youtube_channel (id, profile_picture, name, channel_description) VALUES (:channelID, :image, :name, :channel_description)";
    $saveYoutubeChannelInformation_statement = $pdo->prepare($saveYoutubeChannelInformation);

    $saveYoutubeChannelInformation_statement->bindParam(':channelID', $channelID1);
    $saveYoutubeChannelInformation_statement->bindParam(':image', $image);
    $saveYoutubeChannelInformation_statement->bindParam(':name', $name);
    $saveYoutubeChannelInformation_statement->bindParam(':channel_description', $description);

    $saveYoutubeChannelInformation_statement->execute();

    return $saveYoutubeChannelInformation_statement;
}


function storeChannelVideos($channelID2, $videoInformation, $pdo){
    $videos = json_decode($videoInformation, true);
    $saveChannelVideos = null; // Initialize the variable outside the loop

    if (is_array($videos)) {
        foreach ($videos as $index => $video) {
            $title = $video['title'];
            $link = $video['link'];
            $thumbnail = $video['Thumbnail'];
            $description = $video['Description'];

            $saveChannelVideos = $pdo->prepare("INSERT INTO youtube_channel_videos(channel_id, video_link, title, description, thumbnail) VALUES (:channelID, :link, :title, :description, :thumbnail)");
            $saveChannelVideos->bindParam(':channelID', $channelID2);
            $saveChannelVideos->bindParam(':title', $title);
            $saveChannelVideos->bindParam(':link', $link);
            $saveChannelVideos->bindParam(':thumbnail', $thumbnail);
            $saveChannelVideos->bindParam(':description', $description);
            $saveChannelVideos->execute();
        }
        return $saveChannelVideos;
    }
}

function processVideos($videos){
    // $videos = array_slice($allResults, 0, 100);
    $processedVideos = array_map(function ($processResult) {
        if (isset($processResult['id']['videoId'])) {
            $videoId = $processResult['id']['videoId'];
            $videoTitle = $processResult['snippet']['title'];
            $thumbnailUrl = $processResult['snippet']['thumbnails']['high']['url'];
            $description = $processResult['snippet']['description'];
            $videoLink = "https://www.youtube.com/watch?v=" . $videoId;
        
            return [
                'title' => $videoTitle,
                'link' => $videoLink,
                'Thumbnail' => $thumbnailUrl,
                'Description' => $description
            ];
        }
    }, $videos);

    return $processedVideos;
}

function processVideos1($videos){
    $processedVideos = array_map(function ($processResult) {
        if (isset($processResult->id->videoId)) {
            $videoId = $processResult->id->videoId;
            $videoTitle = $processResult->snippet->title;
            $thumbnailUrl = $processResult->snippet->thumbnails->high->url;
            $description = $processResult->snippet->description;
            $videoLink = "https://www.youtube.com/watch?v=" . $videoId;

            return [
                'title' => $videoTitle,
                'link' => $videoLink,
                'Thumbnail' => $thumbnailUrl,
                'Description' => $description
            ];
        }
    }, $videos);

    return $processedVideos;
}
?>