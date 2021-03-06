<?php
  if (str_replace("\\", "/", __FILE__) === $_SERVER['SCRIPT_FILENAME']) {
    echo "This partial cannot be rendered by itself.";
    exit;
  }
?>
<!--      <div class="front-bg">
        <img class="front-image" src=<?php echo joinPaths(Config::ROOT_URL,"img/front-bg.jpg"); ?> />
      </div> -->
      <div class="row">
        <div class="hero-unit">
          <h1>Welcome to Animurecs!</h1>
          <p>Animurecs is an anime and manga database, built around the idea that watching anime is more fun when you're with friends.</p>
          <p>
            <a href="/register.php" class="btn btn-success btn-lg">
              Sign up today!
            </a>
          </p>
        </div>
      </div>
      <div class="row">
        <ul class="thumbnails">
          <li class="col-md-4">
            <div class="img-thumbnail">
              <div class="caption">
                <h4>Organize your anime</h4>
                <p>
                  Ever forgotten where you last left off watching a series? Animurecs can help you keep track of what you've watched and when you watched it.
                </p>
                <p>
                  <a href='#' class='btn btn-primary'>Take a tour</a>
                </p>
              </div>
            </div>
          </li>
          <li class="col-md-4">
            <div class="img-thumbnail">
              <div class="caption">
                <h4>Get personalized recommendations</h4>
                <p>
                  Animurecs learns what you like from what you've watched, and tailors its recommendations to your tastes so you don't have to go digging to find something you'll like.
                </p>
                <p>
                  <a href='#' class='btn btn-warning'>Try a demo</a>
                </p>
              </div>
            </div>
          </li>
          <li class="col-md-4">
            <div class="img-thumbnail">
              <div class="caption">
                <h4>Keep in touch with friends</h4>
                <p>
                  Find out what your friends have been watching and what they thought of it. Join in on groupwatches with friends, or compete in contests to earn bragging rights!
                </p>
                <p>
                  <a href='#' class='btn btn-info'>Find out more</a>
                </p>
              </div>
            </div>
          </li>
        </ul>
      </div>