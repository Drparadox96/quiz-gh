function getVideos(){
  return JSON.parse(localStorage.getItem("videos") || "[]");
}

function saveVideos(v){
  localStorage.setItem("videos", JSON.stringify(v));
}

function addVideo(){
  const thumb = document.getElementById("thumb").value;
  const link = document.getElementById("link").value;

  if(!thumb || !link) return alert("Fill all fields");

  const vids = getVideos();
  vids.push({
    name: "v" + (vids.length + 1),
    thumb,
    link
  });

  saveVideos(vids);
  alert("Saved");
}

function renderVideos(){
  const grid = document.getElementById("grid");
  if(!grid) return;

  const vids = getVideos();

  grid.innerHTML = vids.map(v=>`
    <div class="card">
      <img src="${v.thumb}" width="200">
      <p>${v.name}</p>
      <a href="interstitial.html?to=${encodeURIComponent(v.link)}">
        Watch
      </a>
    </div>
  `).join("");
}
