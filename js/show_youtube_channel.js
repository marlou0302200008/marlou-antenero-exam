const { createApp } = Vue;
const port = window.location.port;

createApp({

      data() {
        return {
          ChannelName: '',
          videos: [],
          currentPage: 1,
          itemsPerPage: 10,
          loadingImage: 'assets/loading.gif',
          defaultImage: 'assets/default.png'
        };
      },

      computed: {
        // This is for page paginations --START
          totalVideos () {
            return this.videos.length
          },
          totalPages () {
            return Math.ceil(this.totalVideos / this.itemsPerPage)
          },
          displayedVideos () {
            const startIndex = (this.currentPage - 1) * this.itemsPerPage
            const endIndex = startIndex + this.itemsPerPage
            return this.videos.slice(startIndex, endIndex)
          },
          startIndex () {
            return (this.currentPage - 1) * this.itemsPerPage
          }
        // This is for page paginations --END
      },

      mounted() {
        this.searchChannel('@NBA');
      },

      methods: {
        async searchChannel (ChannelName) {
          const loadingImage = document.getElementById('loadingImageDiv')
          const error = document.getElementById('errorDiv')
          const noData = document.getElementById('nodata-tr')

          try {
              loadingImage.style.display = 'block'

              axios.get('http://localhost/youtube/php/sync_youtube_channel.php', {
              params: {
                channelName: ChannelName,
                port: port
              }}).then(response => {

                if(response.data !== 'Error' && response.data.length > 0){
                    try {
                        axios.get('http://localhost/youtube/php/youtube_channel_json.php', {
                        params: {
                          channelID: response.data
                        }}).then(response => {

                          if (response.data.length > 0) {
                            // this codes below will display the information of channel including the videos in the view
                            this.currentPage = 1
                            const currentPageNumber = document.getElementById('currentPage')
                            currentPageNumber.innerHTML = this.currentPage
                            const channelImage = document.getElementById('channelImage')
                            const channelIDInformation = document.getElementById('channelIDInformation')
                            const channelNameInformation = document.getElementById('channelNameInformation')
                            const channelDescriptionInformation = document.getElementById('channelDescriptionInformation')
                            channelImage.src = response.data[0].profile_picture
                            channelIDInformation.href = 'https://www.youtube.com/channel/' + response.data[0].channel_id
                            channelIDInformation.innerHTML = '<i class=\'fa fa-link\' aria-hidden=\'true\' style=\'margin-right: 5px; color: black\'></i>' + 'youtube.com/channel/' + response.data[0].channel_id
                            channelNameInformation.innerHTML = response.data[0].name
                            if (response.data[0].channel_description.length !== 0) {
                              channelDescriptionInformation.innerHTML = '<i class=\'fa fa-align-right\' aria-hidden=\'true\' style=\'margin-right: 5px; color: black\'></i>' + response.data[0].channel_description
                            } else {
                              channelDescriptionInformation.innerHTML = '<i class=\'fa fa-align-right\' aria-hidden=\'true\' style=\'margin-right: 5px; color: black\'></i>' + 'No Description'
                            }
                            this.videos = response.data // this videos will serves as an array which will contain all the first 100 videos in that channel
                            const pageLength = Math.ceil(this.videos.length / 10)
                            const lastPageNumber = document.getElementById('lastPage')
                            lastPageNumber.innerHTML = pageLength
                            loadingImage.style.display = 'none'
                            noData.style.display = 'none'
                          }

                          else if(response.data === 'Empty'){
                            noData.style.display = 'block'
                            console.log('Empty')
                            loadingImage.style.display = 'none'
                          }

                          else{
                            noData.style.display = 'none'
                            console.log(response.data)
                            loadingImage.style.display = 'none'
                            error.style.display = 'block'
                          }

                        });
                    } 
                    
                    catch (error) {
                      console.error(error)
                      noData.style.display = 'none'
                      loadingImage.style.display = 'none'
                      error.style.display = 'block'
                    }
                }

                else{
                  console.log(response.data)
                  noData.style.display = 'none'
                  loadingImage.style.display = 'none'
                  error.style.display = 'block'
                }

              });
          } 
          
          catch (error) {
            noData.style.display = 'none'
            loadingImage.style.display = 'none'
            error.style.display = 'block'
            console.error(error)
          }
        },

        previousPage () {
          const currentPageNumber = document.getElementById('currentPage')
          this.currentPage--
          currentPageNumber.innerHTML = this.currentPage
        },

        nextPage () {
          const currentPageNumber = document.getElementById('currentPage')
          this.currentPage++
          currentPageNumber.innerHTML = this.currentPage
        }
      }

}).mount('#app');