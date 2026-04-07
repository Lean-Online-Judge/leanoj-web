<h2>
  <?= nl2br(htmlspecialchars($user['username'])) ?>
  <span style="font-size: 0.6em">(<?= $user['rating']?>)</span>
</h2>

<div style="text-align: center" id="heatmap"></div>
<div style="text-align: center" id="rating-graph"></div>

<script>
  d3.json("index.php?action=get_submission_data&user=<?= $user['id'] ?>").then(data => {
    const dataMap = d3.rollup(data, v => v[0].count, d => d.date);
    const year = 2026;
    const width = 800;
    const height = 117;
    const cellSize = 15;
    const margin = { top: 20 };

    const svg = d3.select("#heatmap")
      .append("svg")
      .attr("width", width)
      .attr("height", height + margin.top)
      .append("g")
      .attr("transform", `translate(0, ${margin.top})`);

    svg.append("text")
      .attr("class", "year-label")
      .attr("x", -margin.left)
      .attr("y", cellSize * 7 + 12)
      .text(year)
      .style("font-size", "12px")
      .style("fill", "#666");

    const months = d3.timeMonths(new Date(year, 0, 1), new Date(year + 1, 0, 1));

    svg.selectAll(".month-label")
      .data(months)
      .enter()
      .append("text")
      .attr("class", "month-label")
      .attr("x", d => d3.timeWeek.count(d3.timeYear(d), d) * cellSize)
      .attr("y", -5)
      .text(d3.timeFormat("%b"))
      .style("font-size", "10px")
      .style("fill", "#666");

    const dateRange = d3.timeDays(new Date(year, 0, 1), new Date(year + 1, 0, 1));

    const colorScale = d3.scaleThreshold()
      .domain([1, 3, 5]) 
      .range(["#ffffff", "#deebf7", "#9ecae1", "#3182bd"]);

    svg.selectAll("rect")
      .data(dateRange)
      .enter()
      .append("rect")
      .attr("width", cellSize - 2)
      .attr("height", cellSize - 2)
      .attr("x", d => d3.timeWeek.count(d3.timeYear(d), d) * cellSize)
      .attr("y", d => d.getDay() * cellSize)
      .attr("fill", d => {
        const dateKey = d3.timeFormat("%Y-%m-%d")(d);
        const value = dataMap.get(dateKey) || 0;
        return colorScale(value);
      })
      .attr("stroke", "#eee");
  });
</script>

<script>
  d3.json("index.php?action=get_rating_history").then(raw => {
    const data = raw.map(d => ({
      date: new Date(d.date),
      rating: +d.rating
    }));

    const width = 480;
    const height = 240;
    const margin = { top: 10, right: 20, bottom: 10, left: 10 };

    const svg = d3.select("#rating-graph")
      .append("svg")
      .attr("width", width)
      .attr("height", height);

    const x = d3.scaleTime()
      .domain(d3.extent(data, d => d.date))
      .range([margin.left + 32, width - margin.right - 8]);

    const y = d3.scaleLinear()
      .domain([
        d3.min(data, d => d.rating) - 100, 
        d3.max(data, d => d.rating) + 100
      ])
      .range([height - margin.bottom - 28, margin.top + 20]);

    const line = d3.line()
      .x(d => x(d.date))
      .y(d => y(d.rating));

    svg.append("g")
      .attr("transform", `translate(${margin.left - 5},0)`)
      .call(d3.axisLeft(y)
        .ticks(5)
        .tickFormat(d3.format("d"))
        .tickSize(margin.left + margin.right - width)
        .tickPadding(-28))
      .call(g => g.select(".domain").remove())
      .call(g => g.selectAll(".tick line")
        .style("stroke", "#eee"))
      .call(g => g.selectAll(".tick text")
        .attr("fill", "#666"));

    svg.append("g")
      .attr("transform", `translate(0,${height - margin.bottom})`)
      .call(d3.axisBottom(x)
        .ticks(5)
        .tickSize(-height + margin.top + margin.bottom)
        .tickPadding(-14))
      .call(g => g.select(".domain").remove())
      .call(g => g.selectAll(".tick line")
        .style("stroke", "#eee"))
      .call(g => g.selectAll(".tick text")
        .attr("fill", "#666"));

    svg.append("path")
      .datum(data)
      .attr("fill", "none")
      .attr("stroke", "#9ecae1")
      .attr("stroke-width", 2)
      .attr("d", line);

    svg.selectAll("circle")
      .data(data)
      .enter()
      .append("circle")
      .attr("cx", d => x(d.date))
      .attr("cy", d => y(d.rating))
      .attr("r", 3)
      .attr("fill", "#3182bd");

    svg.append("rect")
      .attr("x", margin.left - 5)
      .attr("y", margin.top)
      .attr("width", width - margin.left - margin.right + 15)
      .attr("height", height - margin.top - margin.bottom)
      .attr("fill", "none")
      .attr("stroke", "#aaa")
      .attr("stroke-width", 1)
  });
</script>
