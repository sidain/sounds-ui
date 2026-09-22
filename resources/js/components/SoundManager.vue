<template>
    <div class="sound-manager">
        <div class="util-buttons">
            <!-- Slected Count Label -->
            <div class="selected-count">
                Selected Sounds: {{ audioFiles.filter(file => file.selected).length }}
            </div>
            
            <!-- Submit Action -->
            <button class="submit-btn" @click="submitSelectedSounds">Submit Selected Sounds</button>
            
            <!-- Clear Action -->
            <button class="clear-btn" @click="clearSelection">Clear Selection</button>

            <!-- Reset Views -->
            <button class="reset-btn" @click="resetView">Reset View</button>

            <div class="dropdown-container folders">
                <button @click="isOpen = !isOpen">Filter Folders ▾</button>

                <button>Reset Folders</button>
                <button>Clear All</button>
                <!-- <div v-if="isOpen" class="dropdown-menu"> -->
                <div  class="dropdown-menu">
                    <label v-for="dir in directories" :key="dir" class="dropdown-item">
                    <input @change="filterDirectories" type="checkbox" :value="dir" v-model="selectedDirectories"   />
                    {{ dir }}
                    </label>
                </div>
            </div>

            <div class="dropdown-container zipFiles">
                <button @click="isZipOpen = !isZipOpen">Zip Files▾</button>
                
                <div v-if="isZipOpen" class="dropdown-menu">
                <!-- <div  class="dropdown-menu"> -->
                    <label v-for="zip in unextractedZipFiles" :key="zip" class="dropdown-item">
                        <!-- <input type="checkbox"  /> -->
                        {{ zip }}
                        <button>📥</button>
                    </label>
                </div>
            </div>


            <div class="fileSearch">
                <label>Search: </label>
                <input type="text" />
            </div>
        </div>

        <div class="audio-grid" @scroll="handleScroll">
            <div v-for="file in audioFiles" :key="file.id" class="audio-card" v-show="file.show">
                <label class="card-top">
                    <input type="checkbox" v-model="file.selected" /> 
                    
                    <div class="fileInfo">
                        <div class="file-name"><span>🎵</span> {{ file.name }}</div> 
                    </div>

                    <button class="hide-btn" @click.stop="hideFile(file)" title="Favorite">⭐</button>
                    <button class="hide-btn" @click.stop="hideFile(file)" title="Hide file">❌</button>
                </label>
                
                <div class="fileInfo">
                    <div class="file-directory"><span>🗂️</span> {{ file.directory }}</div> 
                </div>

                <input type="text" v-model="file.label" placeholder="Enter label 🏷️" />
                
                <div class="playControls">
                    <button @click="playSound(file)">▶️ Play</button>
                    <button @click="stopSound(file)">⏹️ Stop</button>                    
                </div>
            </div>

        </div>

        <div class="util-buttons">
            <!-- Slected Count Label -->
            <div class="selected-count">
                Selected Sounds: {{ audioFiles.filter(file => file.selected).length }}
            </div>
            
            <!-- Submit Action -->
            <button class="submit-btn" @click="submitSelectedSounds">Submit Selected Sounds</button>
            
            <!-- Clear Action -->
            <button class="clear-btn" @click="clearSelection">Clear Selection</button>

            <!-- Reset Views -->
            <button class="reset-btn" @click="resetView">Reset View</button>


            

        </div>

    </div>
</template>





<script setup>
import { ref } from 'vue';
import axios from 'axios';
import { computed } from 'vue';
import { onMounted } from 'vue';

const audioFiles = ref([]);
const zipFiles = ref([]);
const directories = ref([]);
const usedDirectories = ref([]);
const selectedDirectories = ref([]);


// Keep track of the currently playing audio element globally in the component
let currentAudio = null;

onMounted(async () => {
    try {
        const response = await axios.get('/api/input-files');
        const allFilePaths = Object.values(response.data.files).flat();

        console.log(response.data);

        // Now we map them into structured objects for your checkboxes and labels
        audioFiles.value = allFilePaths.map((filePath, index) => ({
            id: index,
            name: filePath.split('/').pop(), // Extract filename from path
            directory: filePath.split('/').slice(0, -1).join('/'), // Extract directory from path
            path: `/api/play-audio?path=${encodeURIComponent(filePath)}`,
            selected: false,
            label: filePath,
            show: true,
        }));

        zipFiles.value = response.data.zip_files;
        directories.value = response.data.directories;
        usedDirectories.value = response.data.usedDirectories;

        //need to apply usedDirectories to selectedDirectories
        selectedDirectories.value = [...response.data.usedDirectories];

    } catch (error) {
        console.error("Error fetching audio files:", error);
    }
});

const unextractedZipFiles = computed(() => {
    const zipList = zipFiles.value && typeof zipFiles.value === 'object'
        ? Object.values(zipFiles.value)
        : [];

    return zipList.filter(zip => {
        const zipName = zip.split('/').pop().replace(/\.[^/.]+$/, "").toLowerCase();
        
        const hasMatchingDirectory = directories.value.some(dir => {
            const dirName = dir.split('/').pop().toLowerCase();
            return dirName === zipName;
        });

        return !hasMatchingDirectory;
    });
});


const isOpen = ref(false);
const isZipOpen = ref(false);
const isFoldersOpen = ref(false);

const filterDirectories = async () => {
    console.log('Selected directories:', selectedDirectories.value);

    // Check if any selected directory hasn't been loaded yet
    for (const dir of selectedDirectories.value) {
        if (!usedDirectories.value.includes(dir)) {
            await loadDirectoryOnDemand(dir);
        }
    }

    // Update visibility for all audio cards
    audioFiles.value.forEach(file => {
        file.show = selectedDirectories.value.includes(file.directory);
    });
};

const loadDirectoryOnDemand = async (directory) => {
    try {
        const response = await axios.get('/api/get-files', {
            params: { directory: directory }
        });

        if (response.data && response.data.length > 0) {
            const newFiles = response.data.map((filePath, index) => ({
                id: audioFiles.value.length + index,
                name: filePath.split('/').pop(),
                directory: directory,
                path: `/api/play-audio?path=${encodeURIComponent(filePath)}`,
                selected: false,
                label: filePath,
                show: true,
            }));

            audioFiles.value.push(...newFiles);
            usedDirectories.value.push(directory);
        }
    } catch (error) {
        console.error(`Error loading files for directory ${directory}:`, error);
    }
};

const isLoadingMore = ref(false);
const loadedDirectoryCount = ref(usedDirectories.value.length);

const handleScroll = (e) => {
    const { scrollTop, clientHeight, scrollHeight } = e.target;
    
    // Trigger when within 50px of the bottom and not already loading
    if (scrollHeight - scrollTop - clientHeight < 50 && !isLoadingMore.value) {
        loadMoreFiles();
    }
};

const loadMoreFiles = async () => {
    // Ensure we have more directories left to load and aren't already fetching
    if (isLoadingMore.value || usedDirectories.value.length >= directories.value.length) {
        return;
    }

    isLoadingMore.value = true;
    try {
        // Find the next directory that hasn't been loaded yet
        const nextDirectory = directories.value.find(dir => !usedDirectories.value.includes(dir));
        
        if (!nextDirectory) return;

        // Call your backend endpoint passing the directory parameter
        const response = await axios.get('/api/get-files', {
            params: { directory: nextDirectory }
        });

        if (response.data && response.data.length > 0) {
            // Map the newly fetched file paths into audio cards
            const newFiles = response.data.map((filePath, index) => ({
                id: audioFiles.value.length + index,
                name: filePath.split('/').pop(),
                directory: nextDirectory,
                path: `/api/play-audio?path=${encodeURIComponent(filePath)}`,
                selected: false,
                label: filePath,
                show: true,
            }));

            // Push them into your reactive array
            audioFiles.value.push(...newFiles);
            usedDirectories.value.push(nextDirectory);

            if (!selectedDirectories.value.includes(nextDirectory)) {
                selectedDirectories.value.push(nextDirectory);
            }

        }
    } catch (error) {
        console.error("Error loading more files from directory:", error);
    } finally {
        isLoadingMore.value = false;
    }
};



const playSound = (file) => {
    // Stop the currently playing audio if it exists
    if (currentAudio) {
        currentAudio.pause();
        currentAudio.currentTime = 0;
    }

    currentAudio= new Audio(file.path);
    currentAudio.play().catch(error => {
        console.error("Error playing audio:", error);
    });
};

const stopSound = (file) => {
    if (currentAudio) {
        currentAudio.pause();
        currentAudio.currentTime = 0;
        currentAudio = null; // Clear the reference to the stopped audio
    }

    console.log(`Stopping sound: ${file.name}`);
};

const hideFile = (file) => {
    file.show = false;
    stopSound(file);
    console.log(`Hiding file: ${file.name}`);
};

const resetView = () => {
    audioFiles.value.forEach(file => {
        file.show = true;
    });
    console.log("Resetting view to show all files.");
};

const clearSelection = () => {
    audioFiles.value.forEach(file => {
        file.selected = false;
    });
    console.log("Cleared all selections.");
};

const submitSelectedSounds = async () => {
    const selectedFiles = audioFiles.value.filter(file => file.selected);
    console.log("Submitting selected sounds:", selectedFiles);

    // submit to /api/submit-sounds
    const response = await axios.post('/api/submit-sounds', { files: selectedFiles });

    console.log("Server response:", response.data);
};


// Reactive state and logic will go here
</script>





<style scoped>
    .sound-manager {
        /* max-width: 800px; */
        /* max-height: 80vh; */
        margin: 0 auto;
        padding: 20px;
        /* font-family: Arial, sans-serif; */
    }

    .audio-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
        /* grid-template-columns: 1fr; */
        gap: 12px;
        max-height: 70vh;
        overflow-y: auto;
        padding-right: 8px;
        border: 1px solid #ddd;
        padding: 15px;
        border-radius: 8px;
        background: #f9f9f9;
    }

    .audio-card:hover { 
        /* box-shadow: 0 4px 8px rgba(0,0,0,0.1); */
        /* transform: translateY(-2px); */
        /* transition: all 0.2s ease-in-out; */
        border: 1px solid #000fff;
    }

    .audio-card {
        display: flex;
        flex-direction: column; /* 🔄 Stacks items vertically */
        align-items: stretch;
        gap: 10px;
        background: white;
        padding: 15px;
        border-radius: 6px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        border: 1px solid transparent;
    }


    .card-row {
        display: flex;
        align-items: center;
        gap: 10px;
        width: 100%;
    }

    .card-top {
        display: flex;
        align-items: flex-start;
        gap: 12px;
        width: 100%;
        margin-bottom: -10px; /* Adds space between the top row and the label input */
    }

    .audio-card input[type="checkbox"] {
        margin-top: 3px; /* Align neatly with the first text line */
    }

    .audio-card input[type="text"] {
        margin-top: -5px;
    }

    .hide-btn {
        background: transparent;
        border: none;
        cursor: pointer;
        font-size: 14px;
        padding: 0px 0px 0px 0px;
        border-radius: 4px;
        transition: background-color 0.2s;
    }

    .hide-btn:hover {
        background-color: #fee2e2; /* Light red hover effect */
    }

    .fileInfo {
        font-size: 10px;
        display: flex;
        flex-direction: column;
        align-items: flex-start; /* Forces directory to start on the left */
        text-align: left;
        gap: 4px;
        flex-grow: 1;
        word-break: break-all;
    }

    .file-directory {
        color: #555;
        font-weight: 400;
    }

    .playControls {
        display: flex;
        justify-content: center; /* Centers the play/stop buttons */
        gap: 10px;
    }



    .util-buttons{
        margin: 20px 0px;
        /* display: flex; */
        /* align-items: center; */
        /* justify-content: space-between; */
        /* margin-bottom: 20px; */
    }

    .util-buttons button {
        margin: 0px 4px 10px 0px; 
        /* margin-right: 10px; */
        padding: 6px 15px;
        background-color: #2563eb;
        color: white;
        border: none;
        border-radius: 6px;
        cursor: pointer;
        font-weight: bold;
        font-size: 12px;
    }

    .util-buttons button:hover {
        background-color: #1d4ed8;
    }

    .selected-count {
        font-size: 14px;
        font-weight: bold;
        margin: 10px 0px;
    }

    .dropdown-container{
        position: relative;
        display: block;
        margin-bottom: 10px;
    }

    .dropdown-container button {
        background-color: grey;
        cursor: pointer;
        /* padding: 4px 10px; */
        /* color: white; */
        /* border: none; */
        /* border-radius: 4px; */
        /* font-weight: bold; */
        /* font-size: 10px; */
    }


    .dropdown-container.zipFiles .dropdown-menu{        
        position: absolute;
    }

    .zipFiles .dropdown-menu button{
        background: transparent;
        border: none;
        cursor: pointer;
        font-size: 14px;
        padding: 0px 0px 0px 0px;
        border-radius: 4px;
        transition: background-color 0.2s;
        flex-shrink: 0; /* 👈 Prevents the button from shrinking or wrapping */
    }

    .dropdown-container .dropdown-menu{        
        /* position: absolute; */
        background-color: white;
        border: 1px solid #ccc;
        box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        padding: 10px;
        z-index: 1000;
        font-size: 10px;

        display: grid;
        grid-template-columns: repeat(3, minmax(180px, 1fr));
        gap: 8px 16px;
    }

    .zipFiles .dropdown-item {
        padding: 4px;
        background-color: lightblue;
        border-radius:  4px;
        justify-content: space-between; /* 👈 Pushes the text left and the button right */
        width: 250px;
    }

    .dropdown-item {
        display: flex;
        align-items: center;
        gap: 8px;
        cursor: pointer;
        font-size: 11px;
        word-break: break-all;
        
    }

</style>