<?php
    header('Access-Control-Allow-Origin: *'); // Allow requests from any origin
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS'); // Allow specific HTTP methods
    header('Access-Control-Allow-Headers: Content-Type, Authorization'); // Allow specific headers

    include_once 'connection.php';

    $channelID = $_GET['channelID'];

    //this will get those channel information under the given channelID and return it in the view to dsiplauy
    $getChannel = $pdo->prepare("SELECT * FROM youtube_channel INNER JOIN youtube_channel_videos ON youtube_channel.id = youtube_channel_videos.channel_id WHERE youtube_channel.id = :channelID");
    $getChannel->bindParam(':channelID', $channelID, PDO::PARAM_STR);
    $getChannel->execute();

    if ($getChannel) {
        $rows = $getChannel->fetchAll(PDO::FETCH_ASSOC);
        $channelInformation = json_encode($rows, JSON_PRETTY_PRINT);

        header('Content-Type: application/json');

        echo $channelInformation;
    } else {
        echo $channelID;
    }
?>